<?php
class EnvironmentalCalculator {
    // Constants for impact calculations
    const PLASTIC_IMPACT = 0.15;  // kg CO2 per item
    const GLASS_IMPACT = 0.25;     // kg CO2 per item
    const ALUMINUM_IMPACT = 0.35;  // kg CO2 per item
    
    // Calculate the environmental impact score
    // Based on the integral from the PDF: (1/2)∫₀¹√(1/(x²+y²))dx
    // We'll adapt this to calculate a composite score based on recycling frequency and material types
    public static function calculateImpactScore($userId, PDO $pdo) {
        try {
            // Get recycling data
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_items,
                    SUM(CASE WHEN material_type = 'plastic' THEN 1 ELSE 0 END) as plastic_count,
                    SUM(CASE WHEN material_type = 'glass' THEN 1 ELSE 0 END) as glass_count,
                    SUM(CASE WHEN material_type = 'aluminum' THEN 1 ELSE 0 END) as aluminum_count,
                    DATEDIFF(NOW(), MIN(scan_time)) as days_active
                FROM scanned_barcodes
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $data = $stmt->fetch();
            
            if (!$data || $data['total_items'] == 0) {
                return 0;
            }
            
            // Calculate x and y values for the integral
            // x represents time factor (0 to 1 scale)
            $x = min(1, $data['days_active'] / 30); // Normalize to 30 days
            
            // y represents material diversity factor
            $materialDiversity = (
                ($data['plastic_count'] / $data['total_items']) * 
                ($data['glass_count'] / $data['total_items']) * 
                ($data['aluminum_count'] / $data['total_items'])
            );
            $y = sqrt($materialDiversity);
            
            // Simplified implementation of the integral
            // In a real application, you'd implement numerical integration
            $integralValue = 0.5 * log((sqrt(1 + $y*$y) + 1)) / $y;
            
            // Calculate total CO2 saved
            $co2Saved = 
                ($data['plastic_count'] * self::PLASTIC_IMPACT) +
                ($data['glass_count'] * self::GLASS_IMPACT) +
                ($data['aluminum_count'] * self::ALUMINUM_IMPACT);
            
            // Composite score (0-100 scale)
            $score = min(100, $integralValue * $co2Saved * 10);
            
            return round($score, 2);
            
        } catch (PDOException $e) {
            error_log("Impact calculation failed: " . $e->getMessage());
            return 0;
        }
    }
}
?>
