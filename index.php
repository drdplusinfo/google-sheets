<?php
require __DIR__ . '/vendor/autoload.php';

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName('Google Sheets API PHP Quickstart');
    $client->setScopes([Google_Service_Sheets::DRIVE_FILE]);
    $client->setAuthConfig('credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load previously authorized token from a file, if it exists.
    // The file token.json stores the user's access and refresh tokens, and is
    // created automatically when the authorization flow completes for the first
    // time.
    $tokenPath = 'token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // If there is no previous token or it's expired.
    if ($client->isAccessTokenExpired()) {
        // Refresh the token if possible, else fetch a new one.
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // Request authorization from the user.
            $authUrl = $client->createAuthUrl();
            printf("Open the following link in your browser:\n%s\n", $authUrl);
            print 'Enter verification code: ';
            $authCode = trim(fgets(STDIN));

            // Exchange authorization code for an access token.
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
            $client->setAccessToken($accessToken);

            // Check to see if there was an error.
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
        }
        // Save the token to a file.
        if (!file_exists(dirname($tokenPath))) {
            mkdir(dirname($tokenPath), 0700, true);
        }
        file_put_contents($tokenPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

// Get the API client and construct the service object.
$client = getClient();

$drive = new Google_Service_Drive($client);

// GET DIRS OF SPECIFIC NAME
/** @var Google_Collection $files */
$files = $drive->files->listFiles(
    ['q' => "mimeType='application/vnd.google-apps.folder' and name='hokus pokus' and trashed=false"]
);
/** @var Google_Service_Drive_DriveFile $fileFromDrive */
foreach ($files as $fileFromDrive) {
    var_dump([
        'name' => $fileFromDrive->getName(),
        'mimeType' => $fileFromDrive->getMimeType(),
        'id' => $fileFromDrive->getId(),
    ]);
}

$parentId = '';
if ($files->count() > 0) {
    $files->rewind();
    $parentId = $files->current()->getId();
}

$folder = new Google_Service_Drive_DriveFile();
$folder->setName('dole');
if ($parentId) {
    $folder->setParents([$parentId]);
}
$folder->setMimeType('application/vnd.google-apps.folder');
$created = $drive->files->create($folder);

$sheets = new Google_Service_Sheets($client);

// $spreadsheet = new Google_Service_Sheets_Spreadsheet();
// $spreadsheetProperties = new Google_Service_Sheets_SpreadsheetProperties();
// $spreadsheetProperties->setTitle('Test of Google sheets ' . date(DATE_ATOM));
// $spreadsheet->setProperties($spreadsheetProperties);

// $createdSpreadSheet = $sheets->spreadsheets->create($spreadsheet);
$createdSpreadSheet = $sheets->spreadsheets->get('1Z4Naw8-qTGeST4y-qooAbivLYaxAuyCyMR6_C6XAmdQ'); // TODO remove
/** @var Google_Service_Sheets_Sheet $firstSheet */
$firstSheet = current($createdSpreadSheet->getSheets());

$spreadsheetId = $createdSpreadSheet->getSpreadsheetId();
$range = $firstSheet->getProperties()->getTitle();

$valueRange = new Google_Service_Sheets_ValueRange();
$valueRange->setValues([['co', 'to', 'je']]);
$sheets->spreadsheets_values->update($spreadsheetId, $range, $valueRange, ['valueInputOption' => 'USER_ENTERED']);

$response = $sheets->spreadsheets_values->get($spreadsheetId, $range);
$values = $response->getValues();

var_dump($values);

$requests = [];
// MAKE FIRST ROW BOLD AND CENTERED
$repeatCellRequest = new Google_Service_Sheets_RepeatCellRequest();
$repeatCellRequest->setFields("userEnteredFormat(textFormat,horizontalAlignment)");
$repeatCellRequestRange = new Google_Service_Sheets_GridRange();
$repeatCellRequestRange->setStartRowIndex(0);
$repeatCellRequestRange->setEndRowIndex(1);
$repeatCellRequest->setRange($repeatCellRequestRange);
$cell = new Google_Service_Sheets_CellData();
$cellFormat = new Google_Service_Sheets_CellFormat();
$cellFormat->setHorizontalAlignment("CENTER");
$textFormat = new Google_Service_Sheets_TextFormat();
$textFormat->setBold(true);
$cellFormat->setTextFormat($textFormat);
$cell->setUserEnteredFormat($cellFormat);
$repeatCellRequest->setCell($cell);
$request = new Google_Service_Sheets_Request();
$request->setRepeatCell($repeatCellRequest);
$requests[] = $request;

// PROTECTED RANGE - is NOT protected for the owner
$protectedRangeRequest = new Google_Service_Sheets_AddProtectedRangeRequest();
$protectedRange = new Google_Service_Sheets_ProtectedRange();
$gridRange = new Google_Service_Sheets_GridRange();
$gridRange->setStartRowIndex(0);
$gridRange->setEndRowIndex(1);
$protectedRange->setRange($gridRange);
$protectedRange->setDescription("Na hlavičku nesahej");
$protectedRangeRequest->setProtectedRange($protectedRange);
$request = new Google_Service_Sheets_Request();
$request->setAddProtectedRange($protectedRangeRequest);
$requests[] = $request;

// FREEZE FIRST ROW AS A HEADER
$updateSheetPropertiesRequest = new Google_Service_Sheets_UpdateSheetPropertiesRequest();
$sheetProperties = new Google_Service_Sheets_SheetProperties();
$gridProperties = new Google_Service_Sheets_GridProperties();
$gridProperties->setFrozenRowCount(1);
$sheetProperties->setGridProperties($gridProperties);
$updateSheetPropertiesRequest->setFields("gridProperties.frozenRowCount");
$updateSheetPropertiesRequest->setProperties($sheetProperties);
$request = new Google_Service_Sheets_Request();
$request->setUpdateSheetProperties($updateSheetPropertiesRequest);
$requests[] = $request;

$update = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest();
$update->setRequests($requests);
$batchUpdateResponse = $sheets->spreadsheets->batchUpdate($spreadsheetId, $update);

// LINK TO EDIT IN BROWSER
$file = $drive->files->get($spreadsheetId, ['fields' => 'webViewLink']);
var_dump($file->getWebViewLink());

// DOWNLOAD XLSX
/** @var \GuzzleHttp\Psr7\Response $response */
$response = $drive->files->export($spreadsheetId, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
$body = $response->getBody();
var_dump($body->getSize(), $body->isReadable());
$body->rewind();
$content = '';
while (!$body->eof()) {
    $content .= $body->read(1024);
}
$excelFileName = uniqid('GoogleSheet', true) . '.xlsx';
file_put_contents($excelFileName, $content);

// IMPORT XLSX
$spreadSheetParents = $drive->files->get($spreadsheetId)->getParents();
var_dump($spreadSheetParents);
$uploadFile = new Google_Service_Drive_DriveFile();
$uploadFile->setMimeType('application/vnd.google-apps.spreadsheet');
// $uploadFile->setParents($spreadSheetDirParentId);
$uploadFile->setName($excelFileName);
$createdExcelToSheetsFile = $drive->files->create(
    $uploadFile,
    [
        'data' => file_get_contents($excelFileName),
        'uploadType' => 'multipart',
        'fields' => 'webViewLink',
    ]
);
var_dump("Excel-to-sheets {$createdExcelToSheetsFile->getWebViewLink()}");