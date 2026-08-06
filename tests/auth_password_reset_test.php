<?php
$tmp = tempnam(sys_get_temp_dir(), 'sigma_auth_reset_');
if ($tmp === false) {
    fwrite(STDERR, "Unable to create temp db\n");
    exit(1);
}
unlink($tmp);
putenv('SIGMAIMMO_SQLITE_PATH=' . $tmp);

require_once __DIR__ . '/../app/Database/bootstrap.php';
require_once __DIR__ . '/../app/Services/AuthService.php';
require_once __DIR__ . '/../app/Repositories/UserRepository.php';
require_once __DIR__ . '/../app/Repositories/PasswordResetRepository.php';
require_once __DIR__ . '/../app/Views/LabelProvider.php';
require_once __DIR__ . '/../app/Views/TemplateRenderer.php';

sigma_db();
$users = new UserRepository(sigma_db());
$resets = new PasswordResetRepository(sigma_db());
$auth = new AuthService($users, null, null, $resets);

$user = $auth->register('reset@example.test', 'oldpassword1', 'Reset User');

// Requesting a reset for an unknown email must not throw and must not leak existence.
$auth->requestPasswordReset('unknown@example.test', 'https://sigmaimmo.test/auth/reset-password.php?token=%s');

// Requesting a reset for a known email stores a hashed, time-limited token.
$auth->requestPasswordReset('reset@example.test', 'https://sigmaimmo.test/auth/reset-password.php?token=%s');
$stmt = sigma_db()->prepare('SELECT * FROM password_resets WHERE user_id = :user_id ORDER BY id DESC LIMIT 1');
$stmt->execute(array(':user_id' => $user['id']));
$row = $stmt->fetch();
assertTrue($row !== false, 'a password_resets row was created');
assertTrue($row['token_hash'] !== '' && strlen($row['token_hash']) === 64, 'token is stored hashed (sha256)');
assertTrue($row['used_at'] === null, 'freshly created token is unused');

// An invalid token is rejected without touching the password.
$rejected = false;
try {
    $auth->resetPassword('not-a-real-token', 'newpassword1');
} catch (InvalidArgumentException $e) {
    $rejected = $e->getMessage() === 'auth.invalid_reset_token';
}
assertTrue($rejected, 'an unknown token is rejected');

// A too-short password is rejected even with a well-formed token.
$mailLog = dirname(__DIR__) . '/storage/log/mail.log';
$before = is_file($mailLog) ? filesize($mailLog) : 0;
$auth->requestPasswordReset('reset@example.test', 'https://sigmaimmo.test/auth/reset-password.php?token=%s');
$token = extractLastToken($mailLog, $before);
assertTrue($token !== null, 'reset link was captured from the mail log fallback');

$tooShortRejected = false;
try {
    $auth->resetPassword($token, 'short');
} catch (InvalidArgumentException $e) {
    $tooShortRejected = $e->getMessage() === 'auth.password_too_short';
}
assertTrue($tooShortRejected, 'a too-short new password is rejected');

// A valid token resets the password and can only be used once.
$updated = $auth->resetPassword($token, 'newpassword1');
assertTrue((int)$updated['id'] === (int)$user['id'], 'resetPassword returns the updated user');

$loginOld = false;
try {
    $auth->login('reset@example.test', 'oldpassword1');
    $loginOld = true;
} catch (InvalidArgumentException $e) {
    $loginOld = false;
}
assertTrue(!$loginOld, 'the old password no longer works after reset');

$loggedIn = $auth->login('reset@example.test', 'newpassword1');
assertTrue((int)$loggedIn['id'] === (int)$user['id'], 'the new password works after reset');

$reused = false;
try {
    $auth->resetPassword($token, 'anotherpassword1');
    $reused = true;
} catch (InvalidArgumentException $e) {
    $reused = false;
}
assertTrue(!$reused, 'a used token cannot be replayed');

// Templates render without leftover placeholders, in both request states.
$labels = new LabelProvider(__DIR__ . '/../resources/labels/fr.json');
$renderer = new TemplateRenderer(__DIR__ . '/../app/Views');

$forgotForm = $renderer->render('app/auth/forgot-password', array(
    'labels' => $labels->section('auth.forgot_password'),
    'email' => '',
    'submitted' => false
));
assertTrue(strpos($forgotForm, '{{') === false, 'forgot-password form state has no leftover placeholders');
assertTrue(strpos($forgotForm, 'Mot de passe oublié') !== false, 'forgot-password form renders its title');

$forgotSent = $renderer->render('app/auth/forgot-password', array(
    'labels' => $labels->section('auth.forgot_password'),
    'email' => 'reset@example.test',
    'submitted' => true
));
assertTrue(strpos($forgotSent, '{{') === false, 'forgot-password sent state has no leftover placeholders');
assertTrue(strpos($forgotSent, 'Email envoyé') !== false, 'forgot-password sent state renders confirmation');

$resetForm = $renderer->render('app/auth/reset-password', array(
    'labels' => $labels->section('auth.reset_password'),
    'token' => 'sometoken',
    'has_error' => true,
    'error' => $labels->get('errors.auth.invalid_reset_token'),
    'success' => false
));
assertTrue(strpos($resetForm, '{{') === false, 'reset-password form state has no leftover placeholders');
assertTrue(strpos($resetForm, 'invalide ou a expiré') !== false, 'reset-password renders the invalid-token error');

$resetSuccess = $renderer->render('app/auth/reset-password', array(
    'labels' => $labels->section('auth.reset_password'),
    'token' => '',
    'has_error' => false,
    'error' => '',
    'success' => true
));
assertTrue(strpos($resetSuccess, '{{') === false, 'reset-password success state has no leftover placeholders');
assertTrue(strpos($resetSuccess, 'Mot de passe mis à jour') !== false, 'reset-password success state renders confirmation');

unlink($tmp);
if (is_file($mailLog)) {
    unlink($mailLog);
}
echo "auth_password_reset_test ok\n";

function extractLastToken($mailLog, $offset)
{
    if (!is_file($mailLog)) {
        return null;
    }
    $contents = file_get_contents($mailLog);
    $contents = substr($contents, $offset);
    $lines = array_filter(explode("\n", trim($contents)));
    $last = end($lines);
    if ($last === false) {
        return null;
    }
    $record = json_decode($last, true);
    if (!isset($record['body']) || !preg_match('/token=([a-f0-9]+)/', $record['body'], $m)) {
        return null;
    }
    return $m[1];
}

function assertTrue($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: " . $message . "\n");
        exit(1);
    }
}
