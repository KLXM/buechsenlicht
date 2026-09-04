<?php
/**
 * @var string $instanceId
 * @var array  $config
 * @var bool   $emitAssets
 * @var bool   $loadUikitCdn
 * @var \rex_addon $addon
 */
?>
<?php if ($emitAssets): ?>
<?php if ($loadUikitCdn): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/uikit@3.21.6/dist/css/uikit.min.css">
<script src="https://cdn.jsdelivr.net/npm/uikit@3.21.6/dist/js/uikit.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/uikit@3.21.6/dist/js/uikit-icons.min.js"></script>
<?php endif; ?>
<link rel="stylesheet" href="<?= \rex_escape(\rex_url::addonAssets('buechsenlicht', 'buechsenlicht.css')) ?>">
<script src="<?= \rex_escape(\rex_url::addonAssets('buechsenlicht', 'buechsenlicht.js')) ?>" defer></script>
<?php endif; ?>

<div class="bl-app" id="<?= \rex_escape($instanceId) ?>" data-bl-config="<?= \rex_escape(json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">

    <div class="uk-alert bl-legal-note" uk-alert>
        <p>
            <span uk-icon="icon: info"></span> <?= \rex_escape($config['i18n']['legalNote']) ?>
            <a class="bl-sources-link" href="#<?= \rex_escape($instanceId) ?>-sources" uk-toggle>Datenquellen</a>
        </p>
    </div>

    <div id="<?= \rex_escape($instanceId) ?>-sources" uk-modal>
        <div class="uk-modal-dialog uk-modal-body">
            <button class="uk-modal-close-default" type="button" uk-close></button>
            <h3>Datenquellen je Bundesland</h3>
            <p class="uk-text-small uk-text-muted">
                Diese App unterstützt alle 16 deutschen Bundesländer für die Wildarten Rehwild
                (Böcke, Schmalrehe, Ricken, Kitze), Rotwild, Damwild/Sikawild, Muffelwild und
                Waschbär. Für Länder, deren amtliches Rechtsportal technisch nicht auslesbar war
                (⚠), stammen die Werte aus einer genannten, amtlich anerkannten Drittquelle statt
                aus dem direkt gelesenen Verordnungstext.
            </p>
            <p class="uk-text-small uk-text-muted">
                Regelt ein Bundesland eine Wildart nicht eigenständig, gilt automatisch die
                Bundesjagdzeitenverordnung weiter (Landesrecht kann Bundesrecht nur ergänzen oder
                verschärfen, nicht durch Schweigen aufheben). Diese Fälle sind in der Tabelle als
                „Bundesjagdzeitenverordnung“-Quelle erkennbar und im Hinweistext der jeweiligen
                Wildart in der App vermerkt – es handelt sich dabei um geltendes Recht, nicht um
                eine Datenlücke.
            </p>
            <div class="uk-overflow-auto">
                <table class="uk-table uk-table-small uk-table-divider bl-sources-table">
                    <thead>
                        <tr>
                            <th>Bundesland</th>
                            <th>Quelle</th>
                            <th>Datenstand</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($config['states'] as $stateData): ?>
                            <tr>
                                <td><?= \rex_escape($stateData['name']) ?></td>
                                <td>
                                    <a href="<?= \rex_escape($stateData['sourceUrl']) ?>" target="_blank" rel="noopener"><?= \rex_escape($stateData['source']) ?></a>
                                    <?php if ($stateData['thirdPartySource']): ?>
                                        <br><span class="uk-text-small uk-text-warning">⚠ Werte aus Drittquelle: <?= \rex_escape($stateData['thirdPartySource']) ?> (amtliches Portal technisch nicht auslesbar)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="uk-text-small"><?= \rex_escape($stateData['datenstand']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="uk-margin">
        <div class="uk-card uk-card-default uk-card-body bl-card">
            <h3 class="uk-card-title">1 · Ort / Revier</h3>

            <div class="uk-margin">
                <label class="uk-form-label" for="<?= \rex_escape($instanceId) ?>-ort">Ort / Revier</label>
                <div class="uk-form-controls bl-ort-wrap">
                    <div class="bl-ort-group">
                        <input class="uk-input bl-ort-input" id="<?= \rex_escape($instanceId) ?>-ort" type="text" placeholder="z. B. Ratingen" autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list">
                        <button class="uk-button uk-button-primary bl-btn-geocode" type="button">
                            <span class="bl-spinner" uk-spinner="ratio: 0.55" hidden></span>
                            <span class="bl-btn-geocode-label">Ort bestimmen</span>
                        </button>
                    </div>
                    <ul class="bl-ort-suggestions" role="listbox" hidden></ul>
                </div>
            </div>

            <div class="bl-geocode-status uk-text-meta" hidden></div>

            <div class="bl-state-result uk-alert uk-alert-success" uk-alert hidden>
                <p><strong>Erkanntes Bundesland:</strong> <span class="bl-state-name"></span></p>
            </div>

            <p class="uk-text-meta uk-margin-small-top bl-geocode-credit">
                Ortssuche: <a href="https://photon.komoot.io/" target="_blank" rel="noopener">Photon (komoot)</a>
                auf Basis von © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap-Mitwirkenden</a>.
            </p>
        </div>
    </div>

    <div class="uk-child-width-1-1 uk-child-width-1-2@m" uk-grid uk-height-match="target: > div > .uk-card">

        <div>
            <div class="uk-card uk-card-default uk-card-body bl-card">
                <h3 class="uk-card-title">2 · Wildart</h3>

                <div class="uk-margin">
                    <label class="uk-form-label" for="<?= \rex_escape($instanceId) ?>-wildart">Wildart / Klasse</label>
                    <div class="uk-form-controls">
                        <select class="uk-select bl-wildart-select" id="<?= \rex_escape($instanceId) ?>-wildart">
                            <?php foreach ($config['speciesOrder'] as $species): ?>
                                <option value="<?= \rex_escape($species) ?>"><?= \rex_escape($species) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="bl-season-info uk-card uk-card-body uk-card-muted">
                    <dl class="uk-description-list">
                        <dt>Bundesland</dt>
                        <dd class="bl-info-bundesland">–</dd>
                        <dt>Wildart / Klasse</dt>
                        <dd class="bl-info-wildart">–</dd>
                        <dt>Jagdzeit</dt>
                        <dd class="bl-info-jagdzeit">–</dd>
                        <dt>Rechtsgrundlage / Datenstand</dt>
                        <dd>
                            <a class="bl-info-quelle-link" href="#" target="_blank" rel="noopener" hidden></a>
                            <span class="bl-info-quelle-text">–</span>
                        </dd>
                    </dl>
                    <p class="bl-info-schnittmenge uk-text-small uk-text-muted" hidden></p>
                    <p class="bl-info-hinweis uk-text-small uk-text-warning" hidden></p>
                </div>
            </div>
        </div>

        <div>
            <div class="uk-card uk-card-default uk-card-body bl-card">
                <h3 class="uk-card-title">3 · Kalender</h3>

                <div class="uk-margin uk-grid-small" uk-grid>
                    <div class="uk-width-1-1">
                        <label><input class="uk-checkbox bl-check-morgens" type="checkbox" checked> morgens eintragen</label>
                        <div class="uk-margin-small-top">
                            <label class="uk-form-label uk-text-small">Minuten vor Sonnenaufgang</label>
                            <input class="uk-input uk-form-small bl-min-vor" type="number" min="0" max="240" step="5" value="90">
                        </div>
                    </div>
                    <div class="uk-width-1-1">
                        <label><input class="uk-checkbox bl-check-abends" type="checkbox" checked> abends eintragen</label>
                        <div class="uk-margin-small-top">
                            <label class="uk-form-label uk-text-small">Minuten nach Sonnenuntergang</label>
                            <input class="uk-input uk-form-small bl-min-nach" type="number" min="0" max="240" step="5" value="90">
                        </div>
                    </div>
                </div>

                <div class="bl-option-error uk-text-danger uk-text-small" hidden>Bitte mindestens „morgens“ oder „abends“ aktivieren.</div>

                <button class="uk-button uk-button-primary uk-width-1-1 uk-margin-small-top bl-btn-generate" type="button" disabled>Kalender erstellen</button>

                <div class="bl-result uk-margin" hidden>
                    <div class="uk-alert uk-alert-primary" uk-alert>
                        <p><strong class="bl-result-count"></strong> Kalendereinträge</p>
                        <p class="bl-result-header"></p>
                        <p class="uk-margin-remove-top">
                            Erster Termin:<br>
                            <span class="bl-result-first"></span>
                        </p>
                        <p class="uk-margin-remove-top">
                            Letzter Termin:<br>
                            <span class="bl-result-last"></span>
                        </p>
                    </div>

                    <button class="uk-button uk-button-secondary uk-width-1-1 bl-btn-download" type="button">ICS herunterladen</button>

                    <details class="uk-margin-small-top bl-subscribe-box">
                        <summary class="uk-text-small">Kalender abonnieren (statt Download)</summary>
                        <p class="uk-text-small uk-margin-small-top">
                            Dieser Link liefert den Kalender live (immer ab heute, 5 Jahre) und aktualisiert sich in
                            der Kalender-App automatisch. Zum Abonnieren öffnen oder kopieren:
                        </p>
                        <a class="bl-subscribe-link uk-button uk-button-default uk-width-1-1" href="#" rel="nofollow">
                            <span uk-icon="icon: bell"></span> Kalender abonnieren (webcal)
                        </a>
                        <input class="uk-input uk-form-small uk-margin-small-top bl-subscribe-url" type="text" readonly onclick="this.select()">
                    </details>
                </div>

                <div class="bl-empty-result uk-alert uk-alert-warning uk-margin" uk-alert hidden>
                    <p>Für diesen Zeitraum wurden keine Kalendereinträge gefunden. Bitte Ort und Wildart prüfen.</p>
                </div>
            </div>
        </div>

    </div>
</div>
