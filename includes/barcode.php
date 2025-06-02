<?php
require_once 'db_connect.php';

class BarcodeScanner {
    private $pdo;
    private $campusLat = 37.7749; // Set your campus coordinates
    private $campusLon = -122.4194;
    private $radiusKm = 1.0;
    private $rateLimit = [
        'maxAttempts' => 20,
        'timeWindow' => 300 // 5 minutes
    ];

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    private function isOnCampus($ip) {
        // In a real implementation, you'd geolocate the IP or use GPS coordinates
        // This is a simplified version for demonstration
        return true;
    }

    private function checkRateLimit($userId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM scan_logs 
            WHERE user_id = ? AND timestamp > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$userId, $this->rateLimit['timeWindow']]);
        $result = $stmt->fetch();
        
        if ($result['attempts'] >= $this->rateLimit['maxAttempts']) {
            throw new Exception("Too many scan attempts. Please try again later.");
        }
    }

    public function scanBarcode($userId, $barcode, $material, $ip) {
        $this->checkRateLimit($userId);
        
        // Validate location
        if (!$this->isOnCampus($ip)) {
            throw new Exception("Scanning only allowed on campus");
        }

        $this->pdo->beginTransaction();
        
        try {
            // Check for duplicate scan with lock
            $stmt = $this->pdo->prepare("
                SELECT * FROM scanned_barcodes 
                WHERE barcode = ? AND user_id = ?
                AND scan_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                FOR UPDATE
            ");
            $stmt->execute([$barcode, $userId]);
            
            if ($stmt->rowCount() > 0) {
                throw new Exception("This item was already scanned recently");
            }

            // Calculate points
            $points = match ($material) {
                'glass' => 10,
                'aluminum' => 7,
                'plastic' => 5,
                default => throw new Exception("Invalid material"),
            };

            // Record scan
            $stmt = $this->pdo->prepare("
                INSERT INTO scanned_barcodes 
                (user_id, barcode, material_type, points_awarded, ip_address) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $barcode, $material, $points, $ip]);

            // Update user points
            $this->pdo->prepare("
                UPDATE users 
                SET points_balance = points_balance + ? 
                WHERE user_id = ?
            ")->execute([$points, $userId]);

            // Log the scan
            $this->pdo->prepare("
                INSERT INTO scan_logs 
                (user_id, points, ip_address)
                VALUES (?, ?, ?)
            ")->execute([$userId, $points, $ip]);

            $this->pdo->commit();
            
            // Get updated points total
            $totalPoints = $this->getUserPoints($userId);
            
            return [
                'success' => true,
                'points' => $points,
                'material' => $material,
                'total_points' => $totalPoints
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Scan failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e instanceof InvalidArgumentException ? 'invalid_input' : 'scan_error'
            ];
        }
    }

    private function getUserPoints($userId) {
        $stmt = $this->pdo->prepare("SELECT points_balance FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }
}
?>
