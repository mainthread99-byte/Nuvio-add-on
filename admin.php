<?php
/**
 * Admin Panel: Manage sites and proxy configuration
 */

session_start();
$configPath = __DIR__ . '/config.json';
$config = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : [];

// Verify password
if (!isset($_SESSION['admin_auth'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        $adminPass = $config['admin_password'] ?? 'changeme';
        if ($_POST['password'] === $adminPass) {
            $_SESSION['admin_auth'] = true;
        } else {
            $error = 'Invalid password';
        }
    }
    
    if (!isset($_SESSION['admin_auth'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Nuvio Admin - Login</title>
            <style>
                body { font-family: Arial; background: #f5f5f5; }
                .login-box { max-width: 400px; margin: 50px auto; background: white; padding: 20px; border-radius: 5px; }
                input { width: 100%; padding: 8px; margin: 10px 0; box-sizing: border-box; }
                button { width: 100%; padding: 10px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; }
                .error { color: red; }
            </style>
        </head>
        <body>
            <div class="login-box">
                <h2>Nuvio Admin Panel</h2>
                <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
                <form method="POST">
                    <label>Admin Password:</label>
                    <input type="password" name="password" required>
                    <button type="submit">Login</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    // Update site toggles
    foreach ($config['sites'] as $key => $site) {
        $config['sites'][$key]['enabled'] = isset($_POST["site_$key"]) ? true : false;
    }
    
    // Update proxy settings
    $config['proxy'] = [
        'enabled' => isset($_POST['proxy_enabled']) ? true : false,
        'url' => $_POST['proxy_url'] ?? '',
        'user' => $_POST['proxy_user'] ?? '',
        'pass' => $_POST['proxy_pass'] ?? ''
    ];
    
    // Update admin password if provided
    if (!empty($_POST['new_password'])) {
        $config['admin_password'] = $_POST['new_password'];
    }
    
    file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $message = 'Settings saved successfully!';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Nuvio Admin Panel</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }
        h1 { color: #333; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 3px; margin-bottom: 20px; }
        fieldset { margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; border-radius: 3px; }
        legend { font-weight: bold; padding: 0 10px; }
        label { display: block; margin: 10px 0; }
        input[type="checkbox"] { margin-right: 10px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 8px; box-sizing: border-box; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 3px; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Nuvio Admin Panel</h1>
        <?php if ($message) echo "<div class='success'>$message</div>"; ?>
        
        <form method="POST">
            <fieldset>
                <legend>Enabled Sites</legend>
                <?php foreach ($config['sites'] as $key => $site): ?>
                    <label>
                        <input type="checkbox" name="site_<?= htmlspecialchars($key) ?>" 
                            <?php if (!empty($site['enabled'])) echo 'checked'; ?>>
                        <?= htmlspecialchars($site['name'] ?? $key) ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            
            <fieldset>
                <legend>Proxy Configuration (Optional)</legend>
                <label>
                    <input type="checkbox" name="proxy_enabled" 
                        <?php if (!empty($config['proxy']['enabled'])) echo 'checked'; ?>>
                    Enable Proxy
                </label>
                <label>
                    Proxy URL (e.g., http://proxy.example.com:8080):
                    <input type="text" name="proxy_url" value="<?= htmlspecialchars($config['proxy']['url'] ?? '') ?>">
                </label>
                <label>
                    Proxy Username:
                    <input type="text" name="proxy_user" value="<?= htmlspecialchars($config['proxy']['user'] ?? '') ?>">
                </label>
                <label>
                    Proxy Password:
                    <input type="password" name="proxy_pass" value="<?= htmlspecialchars($config['proxy']['pass'] ?? '') ?>">
                </label>
            </fieldset>
            
            <fieldset>
                <legend>Admin Settings</legend>
                <label>
                    New Admin Password (leave blank to keep current):
                    <input type="password" name="new_password">
                </label>
            </fieldset>
            
            <button type="submit" name="save">Save Settings</button>
        </form>
    </div>
</body>
</html>
