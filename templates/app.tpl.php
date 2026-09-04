<?php
/**
 * @var string $instanceId
 * @var array  $config
 * @var bool   $emitAssets
 * @var \rex_addon $addon
 *
 * UIkit 3 wird bewusst NICHT von diesem AddOn geladen (weder fest noch per CDN-Fallback) - die
 * Website bindet UIkit 3 bereits selbst ein. Wer dieses AddOn auf einer Website ohne UIkit 3
 * einsetzt, muss es selbst einbinden.
 */
?>
<?php if ($emitAssets): ?>
<?php
// Cache-Buster per Dateiänderungszeit: ohne das würde ein Browser die zuvor geladene assets-URL
// (kein Versions-Query-String) auch nach einem Deploy weiter aus dem HTTP-Cache bedienen.
$blCssMtime = @filemtime(\rex_path::addonAssets('buechsenlicht', 'buechsenlicht.css'));
$blJsMtime = @filemtime(\rex_path::addonAssets('buechsenlicht', 'buechsenlicht.js'));
?>
<link rel="stylesheet" href="<?= \rex_escape(\rex_url::addonAssets('buechsenlicht', 'buechsenlicht.css') . ($blCssMtime ? '?v=' . $blCssMtime : '')) ?>">
<script src="<?= \rex_escape(\rex_url::addonAssets('buechsenlicht', 'buechsenlicht.js') . ($blJsMtime ? '?v=' . $blJsMtime : '')) ?>" defer></script>
<?php endif; ?>

<div class="bl-app" id="<?= \rex_escape($instanceId) ?>" data-bl-config="<?= \rex_escape(json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">

    <div class="uk-alert bl-legal-note" uk-alert>
        <p>
            <span uk-icon="icon: info"></span> <?= \rex_escape($config['i18n']['legalNote']) ?>
            <a class="bl-sources-link" href="#<?= \rex_escape($instanceId) ?>-sources" uk-toggle>Datenquellen</a>
        </p>
    </div>

    <div id="<?= \rex_escape($instanceId) ?>-sources" uk-modal>
        <div class="uk-modal-dialog uk-modal-body bl-modal-themed">
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
                        <textarea class="uk-textarea bl-ort-input" id="<?= \rex_escape($instanceId) ?>-ort" rows="1" placeholder="z. B. Ratingen" autocomplete="off" spellcheck="false" readonly role="combobox" aria-expanded="false" aria-autocomplete="list"></textarea>
                        <button class="uk-button uk-button-primary bl-btn-geocode" type="button">
                            <span class="bl-btn-geocode-icon" uk-icon="icon: location"></span>
                            <span class="bl-spinner" uk-spinner="ratio: 0.55" hidden></span>
                            <span class="bl-btn-geocode-label">Ort bestimmen</span>
                        </button>
                    </div>
                    <ul class="bl-ort-suggestions uk-animation-fade" role="listbox" hidden></ul>
                </div>
            </div>

            <div class="bl-geocode-status uk-text-meta" hidden></div>

            <div class="bl-state-result bl-panel bl-panel-success uk-animation-fade" hidden>
                <p><span uk-icon="icon: check"></span> <strong>Erkanntes Bundesland:</strong> <span class="bl-state-name"></span></p>
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

                <button class="uk-button uk-button-primary uk-width-1-1 uk-margin-small-top bl-btn-generate" type="button" disabled>
                    <span uk-icon="icon: calendar"></span> Kalender erstellen
                </button>

                <button class="uk-button uk-button-default uk-width-1-1 uk-margin-small-top bl-btn-daycheck" type="button" uk-toggle="target: #<?= \rex_escape($instanceId) ?>-daycheck" disabled>
                    <span uk-icon="icon: search"></span> Einzelnen Tag prüfen
                </button>

                <div class="bl-result uk-margin uk-animation-fade" hidden>
                    <div class="bl-panel bl-panel-info">
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

                    <button class="uk-button uk-button-secondary uk-width-1-1 bl-btn-download" type="button">
                        <span uk-icon="icon: download"></span> ICS herunterladen
                    </button>

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
                        <p class="uk-text-small uk-text-muted uk-margin-small-top">
                            Kostenlos, ohne Anmeldung. Der Link enthält Ort, Wildart und deine
                            Einstellungen offen lesbar in der URL - sobald du ihn abonnierst, liegt
                            er bei deiner Kalender-App bzw. deren Cloud-Anbieter (z. B. iCloud,
                            Google, Outlook). Nicht weitergeben, wenn du das nicht möchtest. Mehr
                            dazu unten unter „Rechtliches &amp; Datenschutz“.
                        </p>
                    </details>
                </div>

                <div class="bl-empty-result bl-panel bl-panel-warning uk-margin uk-animation-fade" hidden>
                    <p><span uk-icon="icon: ban"></span> Für diesen Zeitraum wurden keine Kalendereinträge gefunden. Bitte Ort und Wildart prüfen.</p>
                </div>
            </div>
        </div>

    </div>

    <div id="<?= \rex_escape($instanceId) ?>-daycheck" uk-modal>
        <div class="uk-modal-dialog uk-modal-body bl-modal-themed">
            <button class="uk-modal-close-default" type="button" uk-close></button>
            <h3><span uk-icon="icon: search"></span> Einzelnen Tag prüfen</h3>
            <p class="uk-text-small uk-text-muted">
                Prüft für den gewählten Ort und die gewählte Wildart, ob ein bestimmter Tag in der
                Jagdzeit liegt, und zeigt Sonnenauf-/-untergang sowie die Büchsenlicht-Fenster.
            </p>
            <div class="uk-margin">
                <label class="uk-form-label" for="<?= \rex_escape($instanceId) ?>-daycheck-date">Datum</label>
                <div class="uk-form-controls">
                    <input class="uk-input bl-daycheck-date" id="<?= \rex_escape($instanceId) ?>-daycheck-date" type="date">
                </div>
            </div>
            <button class="uk-button uk-button-primary uk-width-1-1 bl-daycheck-check" type="button">
                <span uk-icon="icon: check"></span> Prüfen
            </button>

            <div class="bl-daycheck-result uk-margin" hidden>
                <p>
                    <strong class="bl-daycheck-date-label"></strong> ·
                    <span class="bl-daycheck-species-label"></span> ·
                    <span class="bl-daycheck-state-label"></span>
                </p>
                <div class="bl-daycheck-in-season bl-panel bl-panel-success uk-animation-fade" hidden>
                    <p class="uk-margin-remove-bottom"><span uk-icon="icon: check"></span> Jagdtag - Büchsenlicht:</p>
                    <dl class="uk-description-list bl-daycheck-times">
                        <dt>Sonnenaufgang</dt>
                        <dd class="bl-daycheck-sunrise"></dd>
                        <dt>Sonnenuntergang</dt>
                        <dd class="bl-daycheck-sunset"></dd>
                        <dt class="bl-daycheck-morgen-dt" hidden>Morgens</dt>
                        <dd class="bl-daycheck-morgen-dd" hidden></dd>
                        <dt class="bl-daycheck-abend-dt" hidden>Abends</dt>
                        <dd class="bl-daycheck-abend-dd" hidden></dd>
                    </dl>
                </div>
                <div class="bl-daycheck-out-season bl-panel bl-panel-warning uk-animation-fade" hidden>
                    <p><span uk-icon="icon: ban"></span> Kein Jagdtag für diese Wildart an diesem Tag.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="uk-margin">
        <details class="bl-legal-details">
            <summary class="uk-text-small">Rechtliches &amp; Datenschutz</summary>
            <div class="uk-card uk-card-default uk-card-body bl-card uk-margin-small-top">
                <dl class="uk-description-list bl-legal-list">
                    <dt>Kostenlos &amp; freibleibend</dt>
                    <dd>
                        Ort- und Kalendersuche, Download und Abo sind kostenlos und ohne
                        Registrierung nutzbar. Es besteht kein Anspruch auf Verfügbarkeit,
                        Richtigkeit oder Vollständigkeit. Der Dienst kann jederzeit ohne
                        Ankündigung geändert oder eingestellt werden.
                    </dd>
                    <dt>Haftungsausschluss</dt>
                    <dd>
                        Alle Angaben (Jagdzeiten, Sonnenauf-/-untergang, Büchsenlichtzeiten)
                        erfolgen ohne Gewähr. Dieses Werkzeug ersetzt keine amtliche
                        Rechtsauskunft und keine eigene Prüfung der aktuell gültigen
                        Landesjagdzeitenverordnung sowie örtlicher Anordnungen (z. B.
                        Schonzeitverkürzungen, Elterntierschutz, Schutzgebietsregelungen). Für
                        Schäden aus der Nutzung der berechneten Zeiten wird keine Haftung
                        übernommen, soweit gesetzlich zulässig.
                    </dd>
                    <dt>Datenstand</dt>
                    <dd>
                        Die Jagdzeiten-Datenbasis wurde am <?= \rex_escape($config['researchedAt']) ?>
                        recherchiert (Quelle je Bundesland siehe „Datenquellen“ oben). Änderungen
                        der Landesverordnungen nach diesem Zeitpunkt sind nicht automatisch
                        berücksichtigt.
                    </dd>
                    <dt>Datenschutz – Ortssuche</dt>
                    <dd>
                        Deine Eingabe wird an unseren Server und von dort an den Geocoding-Dienst
                        Photon (komoot, auf Basis von OpenStreetMap) weitergeleitet, um
                        Koordinaten und Bundesland zu ermitteln. Es werden keine Nutzerkonten
                        oder personenbezogenen Profile angelegt; Suchanfragen werden serverseitig
                        nur kurzzeitig (5 Minuten) zwischengespeichert, um den externen Dienst zu
                        entlasten.
                    </dd>
                    <dt>Datenschutz – Kalender-Abo</dt>
                    <dd>
                        Der Abo-Link (<code>webcal://</code>) enthält den gewählten Ort, die
                        Wildart und deine Einstellungen offen lesbar in der URL. Sobald du diesen
                        Link in einer Kalender-App abonnierst, liegt die Kontrolle über diese
                        Daten bei deiner Kalender-App bzw. deren Anbieter (z. B. wird der Link bei
                        iCloud-, Google- oder Outlook-Kalendern häufig mit deren Cloud
                        synchronisiert). Gib den Link nicht an Dritte weiter, wenn du das nicht
                        möchtest, und beachte die Datenschutzbestimmungen deines
                        Kalenderanbieters.
                    </dd>
                </dl>
            </div>
        </details>
    </div>
</div>
