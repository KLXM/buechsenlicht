<?php

namespace Buechsenlicht;

/**
 * Modul-Installation/-Aktualisierung, geteilt zwischen install.php (Erstinstallation) und
 * update.php (Versions-Update) - REDAXO ruft bei einem reinen Versions-Update NUR update.php auf,
 * nicht install.php erneut. Ohne dieses Update-Hook würde eine spätere Änderung an
 * module/module_output.inc bzw. module/module_input.inc bei bestehenden Installationen nie
 * ankommen.
 */
class Installer
{
    public static function syncModule(): void
    {
        $moduleInput = \rex_file::get(\rex_path::addon('buechsenlicht', 'module/module_input.inc'));
        $moduleOutput = \rex_file::get(\rex_path::addon('buechsenlicht', 'module/module_output.inc'));

        if (!is_string($moduleInput) || !is_string($moduleOutput) || '' === $moduleInput || '' === $moduleOutput) {
            return;
        }

        $moduleTable = \rex::getTable('module');
        $moduleName = 'Büchsenlicht-Kalender';
        $moduleKey = 'buechsenlicht_kalender';

        $hasKey = false;
        foreach (\rex_sql::showColumns($moduleTable) as $column) {
            if ('key' === $column['name']) {
                $hasKey = true;
                break;
            }
        }

        $sql = \rex_sql::factory();
        if ($hasKey) {
            $sql->setQuery('SELECT id FROM ' . $moduleTable . ' WHERE `key` = ?', [$moduleKey]);
        } else {
            $sql->setQuery('SELECT id FROM ' . $moduleTable . ' WHERE name = ?', [$moduleName]);
        }

        if ($sql->getRows() > 0) {
            $moduleId = (int) $sql->getValue('id');
            $updateSql = \rex_sql::factory();
            $updateSql->setTable($moduleTable);
            $updateSql->setWhere(['id' => $moduleId]);
            $updateSql->setValue('name', $moduleName);
            $updateSql->setValue('input', $moduleInput);
            $updateSql->setValue('output', $moduleOutput);
            if ($hasKey) {
                $updateSql->setValue('key', $moduleKey);
            }
            $updateSql->addGlobalUpdateFields();
            $updateSql->update();
        } else {
            $insertSql = \rex_sql::factory();
            $insertSql->setTable($moduleTable);
            $insertSql->setValue('name', $moduleName);
            $insertSql->setValue('input', $moduleInput);
            $insertSql->setValue('output', $moduleOutput);
            if ($hasKey) {
                $insertSql->setValue('key', $moduleKey);
            }
            $insertSql->addGlobalCreateFields();
            $insertSql->insert();
        }
    }
}
