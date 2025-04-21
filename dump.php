<?php
$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !is_array($data)) {
  http_response_code(400);
  echo "Invalid data format.";
  exit;
}

$date = date("Y-m-d");
$filename = __DIR__ . "/punch_data_" . date("Ymd") . ".csv";

// CSV generation and saving to server
function saveCSVToServer($data, $filename) {
  $output = fopen($filename, "w");
  if (!$output) {
    http_response_code(500);
    echo "Failed to create CSV file on server.";
    exit;
  }

  // Add headers for User ID, Punch Type, and Timestamp
  fputcsv($output, ["User ID", "Type", "Timestamp"]);

  foreach ($data as $userID => $entries) {
    foreach ($entries as $entry) {
      $type = $entry['type'] ?? '';
      $timestamp = $entry['timestamp'] ?? '';
      fputcsv($output, [$userID, $type, $timestamp]);
    }
  }

  fclose($output);

  // After creating the file, trigger download
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
  header('Content-Length: ' . filesize($filename));
  readfile($filename); // Send file to browser
  unlink($filename); // Optional: Delete the file after sending
  exit;
}

// Google Sheets dump
function dumpToGoogleSheets($data) {
  require __DIR__ . '/vendor/autoload.php';

  $client = new \Google_Client();
  $client->setApplicationName('Punch Clock');
  $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
  $client->setAuthConfig('credentials.json');
  $client->setAccessType('offline');

  $service = new Google_Service_Sheets($client);
  $spreadsheetId = 'YOUR_SPREADSHEET_ID_HERE';
  $sheetName = date("Y-m-d");

  // Prepare values for Google Sheets
  $values = [["User ID", "Type", "Timestamp"]];
  foreach ($data as $userID => $entries) {
    foreach ($entries as $entry) {
      $values[] = [$userID, $entry['type'] ?? '', $entry['timestamp'] ?? ''];
    }
  }

  // Try to add a new sheet for the current date
  try {
    $service->spreadsheets->get($spreadsheetId)->getSheets();
    $batchUpdate = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([ 
      'requests' => [[
        'addSheet' => ['properties' => ['title' => $sheetName]]
      ]]
    ]);
    $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdate);
  } catch (Exception $e) {
    // Sheet might already exist, so ignore the error
  }

  // Prepare data to write to the sheet
  $body = new Google_Service_Sheets_ValueRange([
    'values' => $values
  ]);
  $params = ['valueInputOption' => 'RAW'];
  $range = $sheetName . '!A1';

  // Write data to Google Sheets
  $service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);
  echo "Data dumped to Google Sheets.";
}

if (isset($_GET['sheets'])) {
  dumpToGoogleSheets($data);
} else {
  saveCSVToServer($data, $filename);
}
?>
