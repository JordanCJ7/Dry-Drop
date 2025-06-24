<?php
/**
 * InfinityFree-Compatible Database Export Tool for Dry-Drop
 * Removes foreign key constraints for compatibility with InfinityFree hosting
 */

require_once '../includes/config.php';

// Output headers for download
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="drydrop_infinityfree_backup_' . date('Y-m-d_H-i-s') . '.sql"');

echo "-- Dry-Drop Database Export (InfinityFree Compatible)\n";
echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
echo "-- Database: " . DB_NAME . "\n";
echo "-- Note: Foreign key constraints removed for InfinityFree compatibility\n\n";

echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "START TRANSACTION;\n";
echo "SET time_zone = \"+00:00\";\n\n";

// Get all tables
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

// Function to remove foreign key constraints from CREATE TABLE statement
function removeForeignKeys($createStatement) {
    // Remove CONSTRAINT lines
    $lines = explode("\n", $createStatement);
    $cleanLines = [];
    
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        // Skip CONSTRAINT lines and FOREIGN KEY lines
        if (strpos($trimmedLine, 'CONSTRAINT') === 0 || 
            strpos($trimmedLine, 'FOREIGN KEY') !== false) {
            continue;
        }
        $cleanLines[] = $line;
    }
    
    // Join back and clean up trailing commas
    $result = implode("\n", $cleanLines);
    
    // Remove trailing comma before closing parenthesis
    $result = preg_replace('/,(\s*\))/', '$1', $result);
    
    return $result;
}

// Export each table
foreach ($tables as $table) {
    echo "-- \n";
    echo "-- Table structure for table `$table`\n";
    echo "-- \n\n";
    
    echo "DROP TABLE IF EXISTS `$table`;\n";
    
    // Get CREATE TABLE statement and remove foreign keys
    $result = $conn->query("SHOW CREATE TABLE `$table`");
    $row = $result->fetch_array();
    $createStatement = removeForeignKeys($row[1]);
    
    echo $createStatement . ";\n\n";
    
    // Get table data
    echo "-- \n";
    echo "-- Dumping data for table `$table`\n";
    echo "-- \n\n";
    
    $result = $conn->query("SELECT * FROM `$table`");
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $columns = array_keys($row);
            $values = array_values($row);
            
            // Escape values
            $values = array_map(function($value) use ($conn) {
                if ($value === null) return 'NULL';
                return "'" . $conn->real_escape_string($value) . "'";
            }, $values);
            
            echo "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ");\n";
        }
        echo "\n";
    }
}

echo "COMMIT;\n";
echo "-- Export completed (InfinityFree Compatible)\n";
?>
