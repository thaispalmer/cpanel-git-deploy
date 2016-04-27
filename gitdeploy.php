<?php

function gitClone($gitBinary, $gitUrl, $keyPath, $barePath) {
    $gitParams = 'clone ' . $gitUrl . ' "' . $barePath . '"';
    if ($keyPath) {
        $command = 'ssh-add "' . $keyPath . '" && "' . $gitBinary . '" ' . $gitParams;
        exec("ssh-agent bash -c '". $command . "'");
    }
    else {
        $command = '"' . $gitBinary . '" ' . $gitParams;
        exec($command);
    }
}

function gitPull($gitBinary, $keyPath, $branch, $barePath, $path) {
    $gitCommand = '"' . $gitBinary . '" -C "' . $barePath . '" pull && "' . $gitBinary . '" -C "' . $barePath . '" checkout-index -f --prefix="' . $path . '"';
    if ($keyPath) {
        $command = 'ssh-add "' . $keyPath . '" && ' . $gitCommand;
        exec("ssh-agent bash -c '". $command . "'");
    }
    else {
        exec($gitCommand);
    }
}

$userSettings = [];
$userSettings['homedir'] = './homedir';

/*
// Instantiate the CPANEL object.
require_once "/usr/local/cpanel/php/cpanel.php";

// Connect to cPanel - only do this once.
$cpanel = new CPANEL();

// List information for the account's Site Publisher websites.
$userSettings = $cpanel->uapi('SiteTemplates', 'list_user_settings');
*/

$configFile = $userSettings['homedir'] . '/.gitdeploy/config.json';

if (file_exists($configFile)) {
    $configString = file_get_contents($configFile);
    $config = json_decode($configString, true);
}
else {
    if (!file_exists($userSettings['homedir'] . '/.gitdeploy')) mkdir($userSettings['homedir'] . '/.gitdeploy', 0644, true);
    $config = Array(
        'gitBinary' => '/usr/local/cpanel/3rdparty/bin/git',
        'deploys' => Array()
    );
}

if ($_POST):
    switch ($_POST['action']) {
        case 'insert':
            if ($_POST['git'] && $_POST['path'] && $_POST['branch']) {
                $entryId = uniqid();
                $barePath = $userSettings['homedir'] . '/.gitdeploy/' . $entryId;
                if (!empty($_POST['key'])) {
                    $keyFile = $userSettings['homedir'] . '/.gitdeploy/' . $entryId . '.key';
                    file_put_contents($keyFile, $_POST['key']);
                }
                array_push($config['deploys'], Array(
                    'id' => $entryId,
                    'git' => $_POST['git'],
                    'key' => (empty($_POST['key']) ? null : $keyFile),
                    'path' => $userSettings['homedir'] . '/' . $_POST['path'],
                    'barePath' => $barePath,
                    'branch' => $_POST['branch']
                ));
                file_put_contents($configFile, json_encode($config));
                echo 'Config file saved.';
            }
            else {
                echo 'Missing parameters.';
            }
            break;

        case 'delete':
            if (!empty($_POST['index'])) {
                array_splice($config['deploys'], $_POST['index'] -1, 1);
                file_put_contents($configFile, json_encode($config));
                echo 'Config file saved.';
                // todo: remove files
            }
            else {
                echo 'Missing parameters.';
            }
            break;

        case 'clone':
            if (!empty($_POST['index'])) {
                $deployInfo = $config['deploys'][$_POST['index'] -1];
                gitClone($config['gitBinary'], $deployInfo['git'], $deployInfo['key'], $deployInfo['barePath']);
                echo 'Repository Cloned';
            }
            else {
                echo 'Missing parameters.';
            }
            break;

        case 'pull':
            if (!empty($_POST['index'])) {
                $deployInfo = $config['deploys'][$_POST['index'] -1];
                gitPull($config['gitBinary'], $deployInfo['key'], $deployInfo['branch'], $deployInfo['barePath'], $deployInfo['path']);
                echo 'Deploy successful';
            }
            else {
                echo 'Missing parameters.';
            }
            break;

        default:
            break;
    }
endif;
?>
<form method="post">
    <h3>New deploy</h3>
    <input type="text" value="" placeholder="Git url" name="git"/><br/>
    <input type="text" value="" placeholder="Deploy path" name="path"/><br/>
    <input type="text" value="" placeholder="Branch to fetch" name="branch"/><br/>
    <textarea placeholder="Public key for deploy" name="key"/></textarea><br/>
    <input type="submit" value="Add new deploy"/>
    <input type="hidden" name="action" value="insert"/>
</form>
<hr/>
<?php if (sizeof($config['deploys']) > 0): ?>
<h3>Listing deploys</h3>
<ul>
<?php foreach ($config['deploys'] as $index => $deployInfo): ?>
    <li>
        <form method="post">
            <?php echo $deployInfo['git']; ?> -> <?php echo $deployInfo['path']; ?>
            <select name="action">
                <option value="clone">Clone</option>
                <option value="pull">Pull</option>
                <option value="delete">Remove</option>
            </select>
            <input type="submit" value="Go"/>
            <input type="hidden" name="index" value="<?php echo $index +1 ?>"/>
        </form>
    </li>
<?php endforeach; ?>
</ul>
</form>
<?php else: ?>
<h3>Listing Deploys</h3>
<p>No deploys to show.</p>
<?php endif; ?>
