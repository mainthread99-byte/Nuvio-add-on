<?php
session_start();

$cfgPath = __DIR__ . '/config.json';
if (!file_exists($cfgPath)) {
    file_put_contents($cfgPath, json_encode(["admin_password" => "changeme", "sites" => []], JSON_PRETTY_PRINT));
}
$cfg = json_decode(file_get_contents($cfgPath), true) ?? ["admin_password" => "changeme", "sites" => []];

$adminPass = $cfg['admin_password'] ?? 'changeme';

// simple login
if (isset($_POST['password']) && $_POST['password'] === $adminPass) {
    $_SESSION['auth'] = true;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_GET['logout'])) {
    unset($_SESSION['auth']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (!($_SESSION['auth'] ?? false)) {
    ?>
    <!doctype html>
    <html><head><meta charset="utf-8"><title>Addon Admin Login</title></head><body>
    <h2>Login</h2>
    <form method="post">
      <label>Password: <input type="password" name="password"></label>
      <button type="submit">Login</button>
    </form>
    </body></html>
    <?php
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    foreach ($cfg['sites'] as $key => $site) {
        $cfg['sites'][$key]['enabled'] = isset($_POST['site_' . $key]) ? true : false;
    }
    if (isset($_POST['admin_password']) && $_POST['admin_password'] !== '') {
        $cfg['admin_password'] = $_POST['admin_password'];
    }
    file_put_contents($cfgPath, json_encode($cfg, JSON_PRETTY_PRINT));
    $message = 'Saved.';
}

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Addon Admin</title></head><body>
<h2>Addon Admin Portal</h2>
<?php if ($message): ?><p style="color:green"><?=htmlspecialchars($message)?></p><?php endif; ?>
<form method="post">
  <fieldset>
    <legend>Sites</legend>
    <?php foreach ($cfg['sites'] as $key => $site): ?>
      <div>
        <label>
          <input type="checkbox" name="site_<?=htmlspecialchars($key)?>" <?php if (!empty($site['enabled'])) echo 'checked'; ?>>
          <?=htmlspecialchars($site['name'] ?? $key)?> (<?=htmlspecialchars($key)?>)
        </label>
      </div>
    <?php endforeach; ?>
  </fieldset>
  <div>
    <label>Admin password: <input type="text" name="admin_password" value="<?=htmlspecialchars($cfg['admin_password'] ?? '')?>"></label>
  </div>
  <div>
    <button type="submit" name="save">Save</button>
    <a href="?logout=1">Logout</a>
  </div>
</form>
</body></html>
