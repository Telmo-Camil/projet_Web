<?php

namespace App\Controllers;

use PDO;
use Twig\Environment;
use TCPDF;

class ReportGeneratorController
{
    private $db;
    private $twig;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->db = $db;
        $this->twig = $twig;
    }

    public function index()
    {
        try {
            // Récupérer les catégories pour le formulaire
            $queryCategories = "SELECT id, nom FROM categories ORDER BY nom";
            $stmt = $this->db->query($queryCategories);
            $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Récupérer les rapports générés
            $queryReports = "SELECT 
                r.*,
                CASE 
                    WHEN r.type = 'stock_movements' THEN 'Mouvements de stock'
                    WHEN r.type = 'purchase_forecast' THEN 'Prévisions d''achats'
                    WHEN r.type = 'stock_alerts' THEN 'Alertes de stock'
                END as type_label
                FROM reports r
                ORDER BY r.created_at DESC";
            
            $stmt = $this->db->query($queryReports);
            $reports = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Récupérer les statistiques globales
            $stats = $this->getGlobalStats();

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

    private function getGlobalStats()
    {
        // Calculer les statistiques des 30 derniers jours
        $query = "SELECT 
            (SELECT COUNT(*) FROM stock_sorties 
             WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)) as recent_movements,
            (SELECT COUNT(*) FROM product WHERE quantite < 10) as products_to_order,
            CONCAT(
                CASE 
                    WHEN (
                        SELECT SUM(CASE WHEN type = 'entree' THEN quantity ELSE -quantity END)
                        FROM (
                            SELECT 'entree' as type, quantite as quantity
                            FROM orders 
                            WHERE date_livraison >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                            UNION ALL
                            SELECT 'sortie' as type, quantity
                            FROM stock_sorties
                            WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                        ) as movements
                    ) > 0 THEN '+'
                    ELSE ''
                END,
                COALESCE(
                    (
                        SELECT SUM(CASE WHEN type = 'entree' THEN quantity ELSE -quantity END)
                        FROM (
                            SELECT 'entree' as type, quantite as quantity
                            FROM orders 
                            WHERE date_livraison >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                            UNION ALL
                            SELECT 'sortie' as type, quantity
                            FROM stock_sorties
                            WHERE date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                        ) as movements
                    ), 0
                )
            ) as stock_trend";

        $stmt = $this->db->query($query);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
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

            // Générer le rapport
            $reportData = $this->getReportData($type, $dateStart, $dateEnd, $categories);
            
            // Créer le fichier
            $fileName = $this->createReportFile($reportData, $format);
            
            // Sauvegarder dans la base de données
            $query = "INSERT INTO reports (title, type, date_start, date_end, categories, format, file_path) 
                     VALUES (:title, :type, :date_start, :date_end, :categories, :format, :file_path)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'title' => $reportData['title'],
                'type' => $type,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'categories' => json_encode($categories),
                'format' => $format,
                'file_path' => $fileName
            ]);

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
            $query = "SELECT * FROM reports WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['id' => $id]);
            $report = $stmt->fetch(PDO::FETCH_ASSOC);

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

    private function getReportData($type, $dateStart, $dateEnd, $categories)
    {
        $data = [];
        $data['title'] = '';

        switch ($type) {
            case 'stock_movements':
                $data = $this->getStockMovementsReport($dateStart, $dateEnd, $categories);
                break;
            case 'purchase_forecast':
                $data = $this->getPurchaseForecastReport($dateStart, $dateEnd, $categories);
                break;
            case 'stock_alerts':
                $data = $this->getStockAlertsReport($categories);
                break;
        }

        return $data;
    }

    private function getStockMovementsReport($dateStart, $dateEnd, $categories)
    {
        $categoryCondition = '';
        $params = [
            'start_date' => $dateStart,
            'end_date' => $dateEnd
        ];

        if ($categories !== ['all']) {
            $categoryCondition = "AND p.categories_id IN (" . implode(',', array_map('intval', $categories)) . ")";
        }

        $query = "
            SELECT 
                DATE(m.date) as date,
                m.type,
                p.nom as product_name,
                c.nom as category_name,
                SUM(m.quantity) as quantity
            FROM (
                SELECT date_livraison as date, 'Entrée' as type, quantite as quantity, product_name
                FROM orders 
                WHERE date_livraison BETWEEN :start_date AND :end_date
                UNION ALL
                SELECT date, 'Sortie' as type, quantity, p.nom
                FROM stock_sorties s
                JOIN product p ON s.product_id = p.id
                WHERE date BETWEEN :start_date AND :end_date
            ) m
            JOIN product p ON m.product_name = p.nom
            JOIN categories c ON p.categories_id = c.id
            WHERE 1=1 {$categoryCondition}
            GROUP BY DATE(m.date), m.type, p.nom, c.nom
            ORDER BY date ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $movements = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'title' => 'Mouvements de Stock',
            'data' => $movements,
            'fileName' => 'mouvements_stock_' . date('Y-m-d') . '_' . uniqid()
        ];
    }

    private function getPurchaseForecastReport($dateStart, $dateEnd, $categories)
    {
        $categoryCondition = '';
        if ($categories !== ['all']) {
            $categoryCondition = "WHERE p.categories_id IN (" . implode(',', array_map('intval', $categories)) . ")";
        }

        $query = "
            SELECT 
                p.nom as product_name,
                c.nom as category_name,
                p.quantite as current_stock,
                COALESCE(
                    (SELECT AVG(s.quantity) 
                    FROM stock_sorties s 
                    WHERE s.product_id = p.id 
                    AND s.date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                    ), 0
                ) as avg_monthly_usage,
                CASE 
                    WHEN p.quantite = 0 THEN 'Urgent'
                    WHEN p.quantite < 5 THEN 'Critique'
                    WHEN p.quantite < 10 THEN 'À commander'
                    ELSE 'Normal'
                END as status
            FROM product p
            JOIN categories c ON p.categories_id = c.id
            {$categoryCondition}
            ORDER BY p.quantite ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $forecast = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'title' => 'Prévisions d\'Achats',
            'data' => $forecast,
            'fileName' => 'previsions_achats_' . date('Y-m-d') . '_' . uniqid()
        ];
    }

    private function getStockAlertsReport($categories)
    {
        $categoryCondition = '';
        if ($categories !== ['all']) {
            $categoryCondition = "AND p.categories_id IN (" . implode(',', array_map('intval', $categories)) . ")";
        }

        $query = "
            SELECT 
                p.nom as product_name,
                c.nom as category_name,
                p.quantite as current_stock,
                10 as alert_threshold,
                CASE 
                    WHEN p.quantite = 0 THEN 'Rupture'
                    WHEN p.quantite < 5 THEN 'Critique'
                    WHEN p.quantite < 10 THEN 'Alerte'
            END as alert_level
            FROM product p
            JOIN categories c ON p.categories_id = c.id
            WHERE p.quantite < 10 
            {$categoryCondition}
            ORDER BY p.quantite ASC";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $alerts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'title' => 'Alertes de Stock',
            'data' => $alerts,
            'fileName' => 'alertes_stock_' . date('Y-m-d') . '_' . uniqid()
        ];
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