<?php

namespace App\Controllers;

use PDO;
use Twig\Environment;
use TCPDF;
use App\Models\ReportModel;

class ReportGeneratorController
{
    private $twig;
    private $reportModel;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->twig = $twig;
        $this->reportModel = new ReportModel($db);
    }

    public function index()
    {
        try {
            $categories = $this->reportModel->getCategories();
            $reports = $this->reportModel->getGeneratedReports();
            $stats = $this->reportModel->getGlobalStats();

            echo $this->twig->render('rapport.html.twig', [
                'categories' => $categories,
                'reports' => $reports,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            error_log($e->getMessage());
            echo $this->twig->render('rapport.html.twig', [
                'error_message' => "Une erreur est survenue lors du chargement de la page"
            ]);
        }
    }

    public function generateReport()
    {
        try {
            $type = $_POST['report_type'] ?? null;
            $dateStart = $_POST['date_start'] ?? null;
            $dateEnd = $_POST['date_end'] ?? null;
            $categories = $_POST['categories'] ?? [];
            $format = $_POST['format'] ?? 'pdf';

            if (!$type || !$dateStart || !$dateEnd) {
                throw new \Exception("Paramètres manquants");
            }

            $reportData = $this->reportModel->getReportData($type, $dateStart, $dateEnd, $categories);
            $fileName = $this->createReportFile($reportData, $format);
            
            $this->reportModel->saveReport($reportData, $type, $dateStart, $dateEnd, $categories, $format, $fileName);
            
            $_SESSION['success'] = "Rapport généré avec succès";
            
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: index.php?uri=rapport');
        exit;
    }

    public function downloadReport($id)
    {
        try {
            $report = $this->reportModel->getReportById($id);
            if (!$report) {
                throw new \Exception("Rapport non trouvé");
            }

            $filePath = __DIR__ . '/../../reports/' . $report['file_path'];
            if (!file_exists($filePath)) {
                throw new \Exception("Fichier non trouvé");
            }

            header('Content-Type: ' . $this->getContentType($report['format']));
            header('Content-Disposition: attachment; filename="' . basename($report['file_path']) . '"');
            readfile($filePath);
            exit;

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: index.php?uri=rapport');
            exit;
        }
    }

    public function deleteReport($id)
    {
        try {
            $query = "SELECT file_path FROM reports WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['id' => $id]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($report) {
                // Supprimer le fichier physique
                $filePath = __DIR__ . '/../../reports/' . $report['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                // Supprimer l'entrée de la base de données
                $query = "DELETE FROM reports WHERE id = :id";
                $stmt = $this->db->prepare($query);
                $stmt->execute(['id' => $id]);

                $_SESSION['success'] = "Rapport supprimé avec succès";
            }

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: index.php?uri=rapport');
        exit;
    }

    private function getContentType($format)
    {
        switch ($format) {
            case 'pdf':
                return 'application/pdf';
            case 'excel':
                return 'application/vnd.ms-excel';
            case 'html':
                return 'text/html';
            default:
                return 'application/octet-stream';
        }
    }

    private function createReportFile($reportData, $format)
    {
        // Create reports directory if it doesn't exist
        $reportDir = __DIR__ . '/../../reports';
        if (!file_exists($reportDir)) {
            mkdir($reportDir, 0777, true);
        }

        $fileName = $reportData['fileName'] . '.' . $format;
        $filePath = $reportDir . '/' . $fileName;

        switch ($format) {
            case 'pdf':
                $this->generatePDFReport($reportData, $filePath);
                break;
            case 'excel':
                $this->generateExcelReport($reportData, $filePath);
                break;
            case 'html':
                $this->generateHTMLReport($reportData, $filePath);
                break;
            default:
                throw new \Exception("Format non supporté");
        }

        return $fileName;
    }

    private function generatePDFReport($reportData, $filePath)
    {
        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('Gestion de Stock');
        $pdf->SetAuthor('Système');
        $pdf->SetTitle($reportData['title']);

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(15, 15, 15);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 15);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 11);

        // Add title
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, $reportData['title'], 0, 1, 'C');
        $pdf->Ln(5);

        // Reset font
        $pdf->SetFont('helvetica', '', 11);

        if (!empty($reportData['data'])) {
            // Get column headers from first row
            $headers = array_keys(reset($reportData['data']));
            
            // Calculate column widths
            $colWidth = 180 / count($headers);
            
            // Add headers
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetFont('helvetica', 'B', 10);
            foreach ($headers as $header) {
                $displayHeader = ucfirst(str_replace('_', ' ', $header));
                $pdf->Cell($colWidth, 7, $displayHeader, 1, 0, 'C', true);
            }
            $pdf->Ln();

            // Add data rows
            $pdf->SetFont('helvetica', '', 10);
            foreach ($reportData['data'] as $row) {
                foreach ($row as $value) {
                    $pdf->Cell($colWidth, 6, $value, 1, 0, 'L');
                }
                $pdf->Ln();
            }
        }

        // Add generation date
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 10, 'Généré le ' . date('d/m/Y H:i'), 0, 1, 'R');

        // Save file
        $pdf->Output($filePath, 'F');
    }
}