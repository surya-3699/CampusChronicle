<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/config/google.php';
require_once dirname(__DIR__) . '/config/linkedin.php';

function maskValue(string $value, int $visible = 6): string
{
    $value = trim($value);
    if ($value === '') {
        return '(empty)';
    }

    if (strlen($value) <= ($visible * 2)) {
        return str_repeat('*', max(4, strlen($value)));
    }

    return substr($value, 0, $visible)
        . str_repeat('*', max(4, strlen($value) - ($visible * 2)))
        . substr($value, -$visible);
}

$googleProblem = GoogleAuthConfig::configurationProblem();
$linkedinProblem = LinkedInAuthConfig::configurationProblem();

$googleReady = $googleProblem === null;
$linkedinReady = $linkedinProblem === null;

fwrite(STDOUT, "Campus Chronicle OAuth diagnostics\n");
fwrite(STDOUT, str_repeat('=', 38) . "\n\n");

fwrite(STDOUT, '[Google] ' . ($googleReady ? 'READY' : 'NOT READY') . "\n");
fwrite(STDOUT, 'Client ID: ' . maskValue(GoogleAuthConfig::clientId()) . "\n");
if ($googleProblem !== null) {
    fwrite(STDOUT, 'Reason: ' . $googleProblem . "\n");
}
fwrite(STDOUT, "Authorized JavaScript origin for this local project: http://localhost\n\n");

fwrite(STDOUT, '[LinkedIn] ' . ($linkedinReady ? 'READY' : 'NOT READY') . "\n");
fwrite(STDOUT, 'Client ID: ' . maskValue(LinkedInAuthConfig::clientId()) . "\n");
fwrite(STDOUT, 'Client Secret: ' . (LinkedInAuthConfig::clientSecret() !== '' ? 'present (hidden)' : '(empty)') . "\n");
fwrite(STDOUT, 'Redirect URI: ' . LinkedInAuthConfig::redirectUri() . "\n");
if ($linkedinProblem !== null) {
    fwrite(STDOUT, 'Reason: ' . $linkedinProblem . "\n");
}

fwrite(STDOUT, "\nThis check validates local configuration format only.\n");
fwrite(STDOUT, "Only Google/LinkedIn can confirm that a Client ID actually exists.\n");

exit(($googleReady && $linkedinReady) ? 0 : 1);
