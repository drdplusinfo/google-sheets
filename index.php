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

/** @var Google_Collection $files */
$files = $drive->files->listFiles(['q' => "mimeType='application/vnd.google-apps.folder' and name='hokus pokus' and trashed=false"]);
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

$newSheet = new Google_Service_Sheets_Spreadsheet();
$createdSpreadSheet = $sheets->spreadsheets->create($newSheet, []);
var_dump($createdSpreadSheet->getSpreadsheetId(), $newSheet->getSpreadsheetId());

$sheet = new Google_Service_Sheets_Sheet();
$sheet->setData(['foo', 'bar']);
$createdSpreadSheet->setSheets($sheet);

// Prints the names and majors of students in a sample spreadsheet:
// https://docs.google.com/spreadsheets/d/1nAokoR_U_DtDQvLWdIGpVoKLWcrsMcrTH9xU4yB6l4Q/edit
$spreadsheetId = $createdSpreadSheet->getSpreadsheetId();
$range = 'A1:W5';
$response = $sheets->spreadsheets_values->get($spreadsheetId, $range);
$values = $response->getValues();

var_dump($values);