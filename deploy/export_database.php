<?php
/**
 * Database Export Tool for Dry-Drop
 * Use this to backup your local database before deployment
 */

require_once '../includes/config.php';

// Output headers for download
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="drydrop_backup_' . date('Y-m-d_H-i-s') . '.sql"');

echo "-- Dry-Drop Database Export\n";
echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
echo "-- Database: " . DB_NAME . "\n\n";

// Get all tables
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

// Export each table
foreach ($tables as $table) {
    echo "-- \n";
    echo "-- Table structure for table `$table`\n";
    echo "-- \n\n";
    
    // Get CREATE TABLE statement
    $result = $conn->query("SHOW CREATE TABLE `$table`");
    $row = $result->fetch_array();
    echo $row[1] . ";\n\n";
    
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

echo "-- Export completed\n";
?>
