<?php

$moduleTable = rex::getTable('module');
$moduleKey = 'buechsenlicht_kalender';
$moduleName = 'Büchsenlicht-Kalender';

$sql = rex_sql::factory();
$hasKey = false;
foreach (rex_sql::showColumns($moduleTable) as $column) {
    if ('key' === $column['name']) {
        $hasKey = true;
        break;
    }
}

if ($hasKey) {
    $sql->setQuery('DELETE FROM ' . $moduleTable . ' WHERE `key` = ?', [$moduleKey]);
} else {
    $sql->setQuery('DELETE FROM ' . $moduleTable . ' WHERE name = ?', [$moduleName]);
}
