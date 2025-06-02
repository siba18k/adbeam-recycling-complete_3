<?php
header("Content-Type: application/json");
require_once '../../includes/admin_auth.php';
require_once '../../includes/db_connect.php';

try {
    AdminAuth::checkAdmin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'tables':
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['success' => true, 'data' => $tables]);
            break;
            
        case 'describe':
            $table = $_GET['table'] ?? '';
            if (empty($table)) {
                throw new InvalidArgumentException("Table name required");
            }
            
            // Validate table name to prevent SQL injection
            $stmt = $pdo->query("SHOW TABLES");
            $validTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array($table, $validTables)) {
                throw new InvalidArgumentException("Invalid table name");
            }
            
            $stmt = $pdo->query("DESCRIBE `$table`");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $columns]);
            break;
            
        case 'select':
            $table = $_GET['table'] ?? '';
            $limit = min((int)($_GET['limit'] ?? 50), 1000); // Max 1000 rows
            $offset = (int)($_GET['offset'] ?? 0);
            
            if (empty($table)) {
                throw new InvalidArgumentException("Table name required");
            }
            
            // Validate table name
            $stmt = $pdo->query("SHOW TABLES");
            $validTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array($table, $validTables)) {
                throw new InvalidArgumentException("Invalid table name");
            }
            
            // Get total count
            $countStmt = $pdo->query("SELECT COUNT(*) as total FROM `$table`");
            $total = $countStmt->fetch()['total'];
            
            // Get data
            $stmt = $pdo->query("SELECT * FROM `$table` LIMIT $limit OFFSET $offset");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true, 
                'data' => $data,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
            break;
            
        case 'execute':
            $query = $input['query'] ?? '';
            if (empty($query)) {
                throw new InvalidArgumentException("Query required");
            }
            
            // Basic security: only allow SELECT, INSERT, UPDATE, DELETE
            $query = trim($query);
            $firstWord = strtoupper(explode(' ', $query)[0]);
            $allowedCommands = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];
            
            if (!in_array($firstWord, $allowedCommands)) {
                throw new Exception("Only SELECT, INSERT, UPDATE, DELETE queries are allowed");
            }
            
            // Prevent dangerous operations
            $dangerousPatterns = [
                '/DROP\s+/i',
                '/TRUNCATE\s+/i',
                '/ALTER\s+/i',
                '/CREATE\s+/i',
                '/GRANT\s+/i',
                '/REVOKE\s+/i'
            ];
            
            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $query)) {
                    throw new Exception("Query contains forbidden operations");
                }
            }
            
            try {
                if ($firstWord === 'SELECT') {
                    $stmt = $pdo->query($query);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo json_encode([
                        'success' => true,
                        'data' => $result,
                        'type' => 'select',
                        'rows_affected' => count($result)
                    ]);
                } else {
                    $stmt = $pdo->prepare($query);
                    $stmt->execute();
                    echo json_encode([
                        'success' => true,
                        'type' => 'modify',
                        'rows_affected' => $stmt->rowCount()
                    ]);
                }
            } catch (PDOException $e) {
                throw new Exception("Query error: " . $e->getMessage());
            }
            break;
            
        case 'backup':
            // Simple backup functionality
            $tables = [];
            $stmt = $pdo->query("SHOW TABLES");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            
            $backup = "-- AdBeam Recycling Database Backup\n";
            $backup .= "-- Generated on " . date('Y-m-d H:i:s') . "\n\n";
            
            foreach ($tables as $table) {
                $backup .= "-- Table: $table\n";
                $stmt = $pdo->query("SELECT * FROM `$table`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rows)) {
                    $columns = array_keys($rows[0]);
                    $backup .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";
                    
                    $values = [];
                    foreach ($rows as $row) {
                        $escapedValues = array_map(function($value) use ($pdo) {
                            return $pdo->quote($value);
                        }, array_values($row));
                        $values[] = "(" . implode(', ', $escapedValues) . ")";
                    }
                    $backup .= implode(",\n", $values) . ";\n\n";
                }
            }
            
            echo json_encode([
                'success' => true,
                'backup' => $backup,
                'filename' => 'adbeam_backup_' . date('Y-m-d_H-i-s') . '.sql'
            ]);
            break;
            
        default:
            throw new Exception("Invalid action");
    }
    
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'error_code' => 'invalid_input']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'error_code' => 'server_error']);
}
?>
