(function () {
    'use strict';

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function berlinParts(date) {
        var fmt = new Intl.DateTimeFormat('en-US', {
            timeZone: 'Europe/Berlin',
            hour12: false,
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit',
        });
        var parts = fmt.formatToParts(date).reduce(function (acc, p) {
            acc[p.type] = p.value;
            return acc;
        }, {});
        var hour = parseInt(parts.hour, 10);
        if (24 === hour) {
            hour = 0;
        }
        return {
            year: parseInt(parts.year, 10),
            month: parseInt(parts.month, 10),
            day: parseInt(parts.day, 10),
            hour: hour,
            minute: parseInt(parts.minute, 10),
            second: parseInt(parts.second, 10),
        };
    }

    function berlinIcsStamp(date) {
        var p = berlinParts(date);
        return '' + p.year + pad(p.month) + pad(p.day) + 'T' + pad(p.hour) + pad(p.minute) + pad(p.second);
    }

    function berlinTimeLabel(date) {
        var p = berlinParts(date);
        return pad(p.hour) + ':' + pad(p.minute);
    }

    function berlinDateLabel(date) {
        var p = berlinParts(date);
        return pad(p.day) + '.' + pad(p.month) + '.' + p.year;
    }

    /**
     * NOAA-Sonnenalgorithmus (General Solar Position Calculations), reine UTC-Rechnung.
     * Liefert sunrise/sunset als JS Date (UTC-Instant) für das übergebene Kalenderdatum
     * (Y/M/D werden als UTC-Kalendertag interpretiert - für deutsche Breiten/Längen liegt der
     * Sonnenauf-/-untergang stets weit genug von 00:00 UTC entfernt, sodass keine Datumsverschiebung
     * auftritt).
     */
    function sunTimes(year, month, day, lat, lon) {
        function deg2rad(d) { return d * Math.PI / 180; }
        function rad2deg(r) { return r * 180 / Math.PI; }

        var a = Math.floor((14 - month) / 12);
        var y = year + 4800 - a;
        var m = month + 12 * a - 3;
        var jdn = day + Math.floor((153 * m + 2) / 5) + 365 * y + Math.floor(y / 4) - Math.floor(y / 100) + Math.floor(y / 400) - 32045;
        var jd = jdn;

        var t = (jd - 2451545.0) / 36525.0;

        var l0 = (280.46646 + t * (36000.76983 + t * 0.0003032)) % 360;
        if (l0 < 0) { l0 += 360; }

        var mAnom = 357.52911 + t * (35999.05029 - 0.0001537 * t);
        var e = 0.016708634 - t * (0.000042037 + 0.0000001267 * t);

        var mRad = deg2rad(mAnom);
        var c = Math.sin(mRad) * (1.914602 - t * (0.004817 + 0.000014 * t))
            + Math.sin(2 * mRad) * (0.019993 - 0.000101 * t)
            + Math.sin(3 * mRad) * 0.000289;

        var trueLong = l0 + c;
        var appLong = trueLong - 0.00569 - 0.00478 * Math.sin(deg2rad(125.04 - 1934.136 * t));

        var meanObliq = 23 + (26 + (21.448 - t * (46.815 + t * (0.00059 - t * 0.001813))) / 60) / 60;
        var obliqCorr = meanObliq + 0.00256 * Math.cos(deg2rad(125.04 - 1934.136 * t));

        var declin = rad2deg(Math.asin(Math.sin(deg2rad(obliqCorr)) * Math.sin(deg2rad(appLong))));

        var yVar = Math.pow(Math.tan(deg2rad(obliqCorr / 2)), 2);
        var l0Rad = deg2rad(l0);
        var eqTime = 4 * rad2deg(
            yVar * Math.sin(2 * l0Rad)
            - 2 * e * Math.sin(mRad)
            + 4 * e * yVar * Math.sin(mRad) * Math.cos(2 * l0Rad)
            - 0.5 * yVar * yVar * Math.sin(4 * l0Rad)
            - 1.25 * e * e * Math.sin(2 * mRad)
        );

        var latRad = deg2rad(lat);
        var declinRad = deg2rad(declin);
        var haArg = Math.cos(deg2rad(90.833)) / (Math.cos(latRad) * Math.cos(declinRad)) - Math.tan(latRad) * Math.tan(declinRad);

        if (haArg > 1.0 || haArg < -1.0) {
            return { sunrise: null, sunset: null };
        }

        var haSunrise = rad2deg(Math.acos(haArg));

        var solarNoonFraction = (720 - 4 * lon - eqTime) / 1440;
        var sunriseFraction = solarNoonFraction - haSunrise * 4 / 1440;
        var sunsetFraction = solarNoonFraction + haSunrise * 4 / 1440;

        var baseUtc = Date.UTC(year, month - 1, day, 0, 0, 0);

        return {
            sunrise: new Date(baseUtc + Math.round(sunriseFraction * 86400) * 1000),
            sunset: new Date(baseUtc + Math.round(sunsetFraction * 86400) * 1000),
        };
    }

    function isDateInSeason(monthDay, periods) {
        for (var i = 0; i < periods.length; i++) {
            var start = periods[i][0];
            var end = periods[i][1];
            if (start <= end) {
                if (monthDay >= start && monthDay <= end) { return true; }
            } else {
                if (monthDay >= start || monthDay <= end) { return true; }
            }
        }
        return false;
    }

    function formatPeriods(periods) {
        if (!periods || 0 === periods.length) {
            return 'keine Jagdzeit hinterlegt';
        }
        return periods.map(function (p) {
            return germanDate(p[0]) + '.–' + germanDate(p[1]) + '.';
        }).join(', ');
    }

    function germanDate(mmdd) {
        var parts = mmdd.split('-');
        return parts[1] + '.' + parts[0];
    }

    function escapeIcsText(text) {
        return String(text)
            .replace(/\\/g, '\\\\')
            .replace(/\n/g, '\\n')
            .replace(/;/g, '\\;')
            .replace(/,/g, '\\,');
    }

    function foldLine(line) {
        if (line.length <= 75) { return line; }
        var out = '';
        var first = true;
        while (line.length > 0) {
            var chunkLen = first ? 75 : 74;
            out += (first ? '' : '\r\n ') + line.substring(0, chunkLen);
            line = line.substring(chunkLen);
            first = false;
        }
        return out;
    }

    function vtimezoneBlock() {
        return [
            'BEGIN:VTIMEZONE',
            'TZID:Europe/Berlin',
            'X-LIC-LOCATION:Europe/Berlin',
            'BEGIN:DAYLIGHT',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0200',
            'TZNAME:CEST',
            'DTSTART:19700329T020000',
            'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
            'END:DAYLIGHT',
            'BEGIN:STANDARD',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0100',
            'TZNAME:CET',
            'DTSTART:19701025T030000',
            'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
            'END:STANDARD',
            'END:VTIMEZONE',
        ];
    }

    function buildIcs(calendarName, species, ort, bundeslandName, events, uidNamespace) {
        var lines = [];
        lines.push('BEGIN:VCALENDAR');
        lines.push('VERSION:2.0');
        lines.push('PRODID:-//KLXM Crossmedia//Büchsenlicht-Kalender//DE');
        lines.push('CALSCALE:GREGORIAN');
        lines.push('METHOD:PUBLISH');
        lines.push(foldLine('X-WR-CALNAME:' + escapeIcsText(calendarName)));
        lines.push(foldLine('X-WR-CALDESC:' + escapeIcsText('Planungswerkzeug - keine amtliche Rechtsauskunft. Aktuelle Rechtslage und örtliche Anordnungen prüfen.')));
        lines.push('X-WR-TIMEZONE:Europe/Berlin');
        lines.push('REFRESH-INTERVAL;VALUE=DURATION:P1D');
        lines.push('X-PUBLISHED-TTL:P1D');
        lines = lines.concat(vtimezoneBlock());

        events.forEach(function (ev) {
            var isMorning = 'morgens' === ev.type;
            var summary = 'Büchsenlicht ' + species + ' – ' + (isMorning ? 'morgens' : 'abends');
            var description = species + '; ' + ort + '; ' + bundeslandName + '. Sonnenaufgang '
                + berlinTimeLabel(ev.sunrise) + ' Uhr, Sonnenuntergang ' + berlinTimeLabel(ev.sunset)
                + ' Uhr. Planungswert – aktuelle Rechtslage und örtliche Anordnungen prüfen.';
            var uid = ev.date + '-' + ev.type + '-' + hashString(uidNamespace + '|' + ev.date + '|' + ev.type) + '@buechsenlicht';

            lines.push('BEGIN:VEVENT');
            lines.push('UID:' + uid);
            lines.push('DTSTAMP:' + berlinIcsStampUtc());
            lines.push('DTSTART;TZID=Europe/Berlin:' + berlinIcsStamp(ev.start));
            lines.push('DTEND;TZID=Europe/Berlin:' + berlinIcsStamp(ev.end));
            lines.push(foldLine('SUMMARY:' + escapeIcsText(summary)));
            lines.push(foldLine('DESCRIPTION:' + escapeIcsText(description)));
            lines.push(foldLine('LOCATION:' + escapeIcsText(ort + ', ' + bundeslandName)));
            lines.push('END:VEVENT');
        });

        lines.push('END:VCALENDAR');
        return lines.join('\r\n') + '\r\n';
    }

    function berlinIcsStampUtc() {
        var d = new Date();
        return d.getUTCFullYear() + pad(d.getUTCMonth() + 1) + pad(d.getUTCDate()) + 'T'
            + pad(d.getUTCHours()) + pad(d.getUTCMinutes()) + pad(d.getUTCSeconds()) + 'Z';
    }

    function hashString(str) {
        var hash = 0;
        for (var i = 0; i < str.length; i++) {
            hash = (hash << 5) - hash + str.charCodeAt(i);
            hash |= 0;
        }
        return Math.abs(hash).toString(16).padStart(8, '0').substring(0, 8);
    }

    function todayBerlin() {
        var p = berlinParts(new Date());
        return { year: p.year, month: p.month, day: p.day };
    }

    /**
     * Ermittelt für EINEN einzelnen Kalendertag, ob er in der Jagdzeit liegt, sowie Sonnenauf-/
     * -untergang und die daraus resultierenden Büchsenlicht-Fenster (morgens/abends). Sowohl von
     * generateEvents() (Mehrjahres-Kalender) als auch von der "Einzelnen Tag prüfen"-Funktion im
     * UI genutzt, damit beide exakt dieselbe Logik verwenden.
     *
     * @return {inSeason: boolean, sunrise: Date|null, sunset: Date|null, morgenStart: Date|null, morgenEnd: Date|null, abendStart: Date|null, abendEnd: Date|null}
     */
    function computeDayInfo(year, month, day, periods, lat, lon, morgens, abends, vorMin, nachMin) {
        var result = { inSeason: false, sunrise: null, sunset: null, morgenStart: null, morgenEnd: null, abendStart: null, abendEnd: null };

        var monthDay = pad(month) + '-' + pad(day);
        if (!isDateInSeason(monthDay, periods)) {
            return result;
        }

        var sun = sunTimes(year, month, day, lat, lon);
        if (!sun.sunrise || !sun.sunset) {
            return result;
        }

        result.inSeason = true;
        result.sunrise = sun.sunrise;
        result.sunset = sun.sunset;

        if (morgens) {
            result.morgenStart = new Date(sun.sunrise.getTime() - vorMin * 60000);
            result.morgenEnd = sun.sunrise;
        }
        if (abends) {
            result.abendStart = sun.sunset;
            result.abendEnd = new Date(sun.sunset.getTime() + nachMin * 60000);
        }

        return result;
    }

    function generateEvents(periods, lat, lon, morgens, abends, vorMin, nachMin) {
        var events = [];
        if (0 === periods.length || (!morgens && !abends)) {
            return events;
        }

        var today = todayBerlin();
        var cursor = Date.UTC(today.year, today.month - 1, today.day);
        var end = Date.UTC(today.year + 5, today.month - 1, today.day);

        while (cursor <= end) {
            var d = new Date(cursor);
            var y = d.getUTCFullYear(), m = d.getUTCMonth() + 1, day = d.getUTCDate();

            var info = computeDayInfo(y, m, day, periods, lat, lon, morgens, abends, vorMin, nachMin);
            if (info.inSeason) {
                var dateStr = y + '-' + pad(m) + '-' + pad(day);
                if (info.morgenStart) {
                    events.push({ date: dateStr, type: 'morgens', start: info.morgenStart, end: info.morgenEnd, sunrise: info.sunrise, sunset: info.sunset });
                }
                if (info.abendStart) {
                    events.push({ date: dateStr, type: 'abends', start: info.abendStart, end: info.abendEnd, sunrise: info.sunrise, sunset: info.sunset });
                }
            }

            cursor += 86400000;
        }

        return events;
    }

    function initApp(container) {
        var configRaw = container.getAttribute('data-bl-config');
        var config;
        try {
            config = JSON.parse(configRaw);
        } catch (e) {
            return;
        }

        var els = {
            ortInput: container.querySelector('.bl-ort-input'),
            ortWrap: container.querySelector('.bl-ort-wrap'),
            suggestions: container.querySelector('.bl-ort-suggestions'),
            geocodeBtn: container.querySelector('.bl-btn-geocode'),
            geocodeBtnIcon: container.querySelector('.bl-btn-geocode-icon'),
            geocodeBtnSpinner: container.querySelector('.bl-spinner'),
            geocodeBtnLabel: container.querySelector('.bl-btn-geocode-label'),
            geocodeStatus: container.querySelector('.bl-geocode-status'),
            stateResult: container.querySelector('.bl-state-result'),
            stateName: container.querySelector('.bl-state-name'),
            wildartSelect: container.querySelector('.bl-wildart-select'),
            infoBundesland: container.querySelector('.bl-info-bundesland'),
            infoWildart: container.querySelector('.bl-info-wildart'),
            infoJagdzeit: container.querySelector('.bl-info-jagdzeit'),
            infoQuelleLink: container.querySelector('.bl-info-quelle-link'),
            infoQuelleText: container.querySelector('.bl-info-quelle-text'),
            infoSchnittmenge: container.querySelector('.bl-info-schnittmenge'),
            infoHinweis: container.querySelector('.bl-info-hinweis'),
            seasonInfo: container.querySelector('.bl-season-info'),
            checkMorgens: container.querySelector('.bl-check-morgens'),
            checkAbends: container.querySelector('.bl-check-abends'),
            minVor: container.querySelector('.bl-min-vor'),
            minNach: container.querySelector('.bl-min-nach'),
            optionError: container.querySelector('.bl-option-error'),
            generateBtn: container.querySelector('.bl-btn-generate'),
            result: container.querySelector('.bl-result'),
            resultCount: container.querySelector('.bl-result-count'),
            resultHeader: container.querySelector('.bl-result-header'),
            resultFirst: container.querySelector('.bl-result-first'),
            resultLast: container.querySelector('.bl-result-last'),
            downloadBtn: container.querySelector('.bl-btn-download'),
            subscribeLink: container.querySelector('.bl-subscribe-link'),
            subscribeUrl: container.querySelector('.bl-subscribe-url'),
            subscribeGoogle: container.querySelector('.bl-subscribe-google'),
            subscribeOutlook: container.querySelector('.bl-subscribe-outlook'),
            subscribeCopyBtn: container.querySelector('.bl-subscribe-copy'),
            subscribeCopyStatus: container.querySelector('.bl-subscribe-copy-status'),
            emptyResult: container.querySelector('.bl-empty-result'),
            daycheckBtn: container.querySelector('.bl-btn-daycheck'),
        };

        // uk-modal-Elemente werden von UIkit beim Initialisieren an document.body verschoben, sind
        // danach also keine Nachfahren von container mehr - über die feste ID lokalisieren statt
        // über container.querySelector().
        var daycheckModal = document.getElementById(container.id + '-daycheck');
        var daycheckEls = daycheckModal ? {
            date: daycheckModal.querySelector('.bl-daycheck-date'),
            checkBtn: daycheckModal.querySelector('.bl-daycheck-check'),
            result: daycheckModal.querySelector('.bl-daycheck-result'),
            dateLabel: daycheckModal.querySelector('.bl-daycheck-date-label'),
            speciesLabel: daycheckModal.querySelector('.bl-daycheck-species-label'),
            stateLabel: daycheckModal.querySelector('.bl-daycheck-state-label'),
            inSeason: daycheckModal.querySelector('.bl-daycheck-in-season'),
            outSeason: daycheckModal.querySelector('.bl-daycheck-out-season'),
            sunrise: daycheckModal.querySelector('.bl-daycheck-sunrise'),
            sunset: daycheckModal.querySelector('.bl-daycheck-sunset'),
            morgenDt: daycheckModal.querySelector('.bl-daycheck-morgen-dt'),
            morgenDd: daycheckModal.querySelector('.bl-daycheck-morgen-dd'),
            abendDt: daycheckModal.querySelector('.bl-daycheck-abend-dt'),
            abendDd: daycheckModal.querySelector('.bl-daycheck-abend-dd'),
        } : null;

        var place = null; // { name, lat, lon, stateCode }
        var lastEvents = null;

        function currentSpecies() {
            return els.wildartSelect.value;
        }

        function currentOptions() {
            var vor = parseInt(els.minVor.value, 10);
            var nach = parseInt(els.minNach.value, 10);
            return {
                morgens: els.checkMorgens.checked,
                abends: els.checkAbends.checked,
                vor: isNaN(vor) ? config.defaultVorMin : Math.max(0, Math.min(240, vor)),
                nach: isNaN(nach) ? config.defaultNachMin : Math.max(0, Math.min(240, nach)),
            };
        }

        function flashSeasonInfo() {
            els.seasonInfo.classList.remove('bl-flash');
            void els.seasonInfo.offsetWidth; // Reflow erzwingen, damit die Animation neu startet.
            els.seasonInfo.classList.add('bl-flash');
        }

        function setGeocodeLoading(isLoading) {
            els.geocodeBtn.disabled = isLoading;
            els.geocodeBtnIcon.hidden = isLoading;
            els.geocodeBtnSpinner.hidden = !isLoading;
            els.geocodeBtnLabel.textContent = isLoading ? 'Suche …' : 'Ort bestimmen';
        }

        function updateInfoBox() {
            var species = currentSpecies();
            flashSeasonInfo();

            if (!place) {
                els.infoBundesland.textContent = '–';
                els.infoWildart.textContent = species;
                els.infoJagdzeit.textContent = 'Bitte zuerst Ort bestimmen.';
                els.infoQuelleLink.hidden = true;
                els.infoQuelleText.hidden = false;
                els.infoQuelleText.textContent = '–';
                els.infoSchnittmenge.hidden = true;
                els.infoHinweis.hidden = true;
                return;
            }

            var stateData = config.states[place.stateCode];
            var periods = stateData.species[species] || [];

            els.infoBundesland.textContent = stateData.name;
            els.infoWildart.textContent = species;
            els.infoJagdzeit.textContent = formatPeriods(periods);

            if (stateData.sourceUrl) {
                els.infoQuelleLink.hidden = false;
                els.infoQuelleLink.href = stateData.sourceUrl;
                els.infoQuelleLink.textContent = stateData.source + ' · Datenstand ' + stateData.datenstand;
                els.infoQuelleText.hidden = true;
            } else {
                els.infoQuelleLink.hidden = true;
                els.infoQuelleText.hidden = false;
                els.infoQuelleText.textContent = stateData.source + ' · Datenstand ' + stateData.datenstand;
            }

            if ('Rehwild · alle Klassen' === species) {
                els.infoSchnittmenge.hidden = false;
                els.infoSchnittmenge.textContent = 'Schnittmenge: In diesem Zeitraum haben Böcke, Schmalrehe, Ricken und Kitze gleichzeitig Jagdzeit.';
            } else {
                els.infoSchnittmenge.hidden = true;
            }

            if (stateData.hinweis) {
                els.infoHinweis.hidden = false;
                els.infoHinweis.textContent = 'Hinweis: ' + stateData.hinweis;
            } else {
                els.infoHinweis.hidden = true;
            }

            evaluateGenerateAvailability();
        }

        function evaluateGenerateAvailability() {
            var opts = currentOptions();
            var ok = !!place && (opts.morgens || opts.abends);
            els.generateBtn.disabled = !ok;
            els.daycheckBtn.disabled = !place;
        }

        function enforceAtLeastOneCheckbox(changed) {
            if (!els.checkMorgens.checked && !els.checkAbends.checked) {
                changed.checked = true;
                els.optionError.hidden = false;
                setTimeout(function () { els.optionError.hidden = true; }, 3000);
            }
            evaluateGenerateAvailability();
        }

        els.checkMorgens.addEventListener('change', function () { enforceAtLeastOneCheckbox(els.checkMorgens); });
        els.checkAbends.addEventListener('change', function () { enforceAtLeastOneCheckbox(els.checkAbends); });
        els.minVor.addEventListener('change', evaluateGenerateAvailability);
        els.minNach.addEventListener('change', evaluateGenerateAvailability);
        els.wildartSelect.addEventListener('change', updateInfoBox);

        function showGeocodeStatus(msg, isError) {
            if ('' === msg) {
                els.geocodeStatus.hidden = true;
                return;
            }
            els.geocodeStatus.hidden = false;
            els.geocodeStatus.textContent = msg;
            els.geocodeStatus.classList.toggle('uk-text-danger', !!isError);
        }

        function geocodeUrl(query, limit) {
            return config.geocodeBase + '&limit=' + limit + '&q=' + encodeURIComponent(query);
        }

        // Übernimmt einen Nominatim-Treffer (aus Vorschlagsliste oder "Ort bestimmen") als
        // gewählten Ort, sofern sich ein unterstütztes Bundesland zuordnen lässt.
        function applyPlace(hit) {
            var address = hit.address || {};
            var stateNameRaw = address.state || '';
            var stateCode = config.stateNameMap[stateNameRaw.toLowerCase()] || null;

            if (!stateCode) {
                place = null;
                els.stateResult.hidden = true;
                showGeocodeStatus('Bundesland konnte nicht zugeordnet werden ("' + (stateNameRaw || 'unbekannt') + '"). Bitte einen Ort in Deutschland eingeben.', true);
                evaluateGenerateAvailability();
                return;
            }

            var resolvedName = address.city || address.town || address.village || address.municipality || hit.display_name.split(',')[0];

            place = {
                name: resolvedName,
                lat: parseFloat(hit.lat),
                lon: parseFloat(hit.lon),
                stateCode: stateCode,
            };

            showGeocodeStatus('', false);
            els.stateResult.hidden = false;
            els.stateName.textContent = config.states[stateCode].name;
            els.ortInput.value = resolvedName;

            updateInfoBox();
        }

        els.geocodeBtn.addEventListener('click', function () {
            var query = (els.ortInput.value || '').trim();
            if ('' === query) {
                showGeocodeStatus('Bitte einen Ort eingeben.', true);
                return;
            }

            place = null;
            els.stateResult.hidden = true;
            els.result.hidden = true;
            hideSuggestions();
            evaluateGenerateAvailability();
            showGeocodeStatus('', false);
            setGeocodeLoading(true);

            fetch(geocodeUrl(query, 1), { headers: { 'Accept': 'application/json' } })
                .then(function (res) {
                    if (!res.ok) { throw new Error('network'); }
                    return res.json();
                })
                .then(function (data) {
                    setGeocodeLoading(false);
                    if (!data || 0 === data.length) {
                        showGeocodeStatus('Ort nicht gefunden. Bitte Schreibweise prüfen.', true);
                        return;
                    }
                    applyPlace(data[0]);
                })
                .catch(function () {
                    setGeocodeLoading(false);
                    showGeocodeStatus('Die Ortssuche ist derzeit nicht erreichbar. Bitte später erneut versuchen.', true);
                });
        });

        // --- Live-Vorschläge während der Eingabe ---
        var suggestionTimer = null;
        var suggestionRequestId = 0;
        var currentSuggestions = [];
        var activeSuggestionIndex = -1;

        function hideSuggestions() {
            els.suggestions.hidden = true;
            els.suggestions.innerHTML = '';
            currentSuggestions = [];
            activeSuggestionIndex = -1;
            els.ortInput.setAttribute('aria-expanded', 'false');
        }

        function renderSuggestions(hits) {
            currentSuggestions = hits;
            activeSuggestionIndex = -1;
            els.suggestions.innerHTML = '';

            if (0 === hits.length) {
                hideSuggestions();
                return;
            }

            hits.forEach(function (hit, index) {
                var address = hit.address || {};
                var mainName = address.city || address.town || address.village || address.municipality || hit.display_name.split(',')[0];
                var stateNameRaw = address.state || '';

                var li = document.createElement('li');
                li.setAttribute('role', 'option');

                var btn = document.createElement('button');
                btn.type = 'button';
                btn.dataset.index = String(index);

                var main = document.createElement('span');
                main.textContent = mainName;
                btn.appendChild(main);

                if (stateNameRaw) {
                    var sub = document.createElement('span');
                    sub.className = 'bl-suggestion-sub';
                    sub.textContent = stateNameRaw;
                    btn.appendChild(sub);
                }

                btn.addEventListener('mousedown', function (ev) {
                    // mousedown statt click: verhindert, dass der vorherige blur-Handler die Liste
                    // schon vor der Auswahl ausblendet.
                    ev.preventDefault();
                    hideSuggestions();
                    applyPlace(hit);
                });

                li.appendChild(btn);
                els.suggestions.appendChild(li);
            });

            els.suggestions.hidden = false;
            els.ortInput.setAttribute('aria-expanded', 'true');
        }

        function setActiveSuggestion(index) {
            var items = els.suggestions.querySelectorAll('li');
            items.forEach(function (li, i) {
                li.classList.toggle('bl-active', i === index);
            });
            activeSuggestionIndex = index;
        }

        // Chrome bietet auf Feldern, die wie ein Orts-/Adressfeld aussehen (Label "Ort", Platzhalter
        // mit Städtenamen), sein eigenes profilbasiertes Autofill an - autocomplete="off" allein wird
        // dafür von Chrome bewusst ignoriert. "readonly bis zum ersten Fokus" verhindert zuverlässig,
        // dass Chrome die Autofill-UI beim Laden der Seite an das Feld anhängt.
        function enableOrtInput() {
            els.ortInput.removeAttribute('readonly');
        }
        els.ortInput.addEventListener('focus', enableOrtInput, { once: true });
        els.ortInput.addEventListener('mousedown', enableOrtInput, { once: true });

        els.ortInput.addEventListener('input', function () {
            var query = els.ortInput.value.trim();
            place = null;
            els.stateResult.hidden = true;
            evaluateGenerateAvailability();

            if (suggestionTimer) {
                clearTimeout(suggestionTimer);
            }

            if (query.length < 3) {
                hideSuggestions();
                return;
            }

            var requestId = ++suggestionRequestId;
            suggestionTimer = setTimeout(function () {
                fetch(geocodeUrl(query, 5), { headers: { 'Accept': 'application/json' } })
                    .then(function (res) {
                        if (!res.ok) { throw new Error('network'); }
                        return res.json();
                    })
                    .then(function (data) {
                        if (requestId !== suggestionRequestId) { return; } // veraltete Antwort ignorieren
                        renderSuggestions(Array.isArray(data) ? data : []);
                    })
                    .catch(function () {
                        if (requestId === suggestionRequestId) { hideSuggestions(); }
                    });
            }, 400);
        });

        els.ortInput.addEventListener('keydown', function (ev) {
            // .bl-ort-input ist ein <textarea> (siehe CSS-Kommentar); Enter darf dort nie einen
            // Zeilenumbruch einfügen, unabhängig vom Vorschlags-Dropdown.
            if ('Enter' === ev.key) {
                ev.preventDefault();
            }

            if (els.suggestions.hidden || 0 === currentSuggestions.length) {
                if ('Enter' === ev.key) {
                    els.geocodeBtn.click();
                }
                return;
            }

            if ('ArrowDown' === ev.key) {
                ev.preventDefault();
                setActiveSuggestion(Math.min(activeSuggestionIndex + 1, currentSuggestions.length - 1));
            } else if ('ArrowUp' === ev.key) {
                ev.preventDefault();
                setActiveSuggestion(Math.max(activeSuggestionIndex - 1, 0));
            } else if ('Enter' === ev.key) {
                if (activeSuggestionIndex >= 0) {
                    var hit = currentSuggestions[activeSuggestionIndex];
                    hideSuggestions();
                    applyPlace(hit);
                } else {
                    hideSuggestions();
                    els.geocodeBtn.click();
                }
            } else if ('Escape' === ev.key) {
                hideSuggestions();
            }
        });

        els.ortInput.addEventListener('blur', function () {
            // Verzögert, damit ein mousedown auf einen Vorschlag noch verarbeitet wird.
            setTimeout(hideSuggestions, 150);
        });

        document.addEventListener('click', function (ev) {
            if (!els.ortWrap.contains(ev.target)) {
                hideSuggestions();
            }
        });

        els.generateBtn.addEventListener('click', function () {
            if (!place) { return; }
            var species = currentSpecies();
            var opts = currentOptions();
            if (!opts.morgens && !opts.abends) { return; }

            var periods = config.states[place.stateCode].species[species] || [];
            var events = generateEvents(periods, place.lat, place.lon, opts.morgens, opts.abends, opts.vor, opts.nach);

            lastEvents = { events: events, species: species, opts: opts };

            if (0 === events.length) {
                els.result.hidden = true;
                els.emptyResult.hidden = false;
                return;
            }
            els.emptyResult.hidden = true;

            events.sort(function (a, b) { return a.start - b.start; });

            var first = events[0];
            var last = events[events.length - 1];

            els.resultCount.textContent = events.length.toLocaleString('de-DE');
            els.resultHeader.textContent = place.name + ' · ' + config.states[place.stateCode].name + ' · ' + species;
            els.resultFirst.textContent = berlinDateLabel(first.start) + ' · ' + first.type + ' · ' + berlinTimeLabel(first.start) + '–' + berlinTimeLabel(first.end) + ' Uhr';
            els.resultLast.textContent = berlinDateLabel(last.start) + ' · ' + last.type + ' · ' + berlinTimeLabel(last.start) + '–' + berlinTimeLabel(last.end) + ' Uhr';

            els.result.hidden = false;

            updateSubscribeLink(species, opts);
        });

        els.downloadBtn.addEventListener('click', function () {
            if (!lastEvents || !place) { return; }

            var species = lastEvents.species;
            var stateName = config.states[place.stateCode].name;
            var calendarName = 'Büchsenlicht · ' + species + ' · ' + place.name;
            var uidNamespace = [place.stateCode, species, place.lat, place.lon, lastEvents.opts.vor, lastEvents.opts.nach].join('|');

            var ics = buildIcs(calendarName, species, place.name, stateName, lastEvents.events, uidNamespace);
            var blob = new Blob([ics], { type: 'text/calendar;charset=utf-8' });
            var filename = 'Buechsenlicht_' + slug(place.name) + '_' + slug(species) + '.ics';

            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
        });

        function updateSubscribeLink(species, opts) {
            if (!place) { return; }
            // config.feedBase kommt von rex_url::frontendController() und ist absichtlich
            // relativ zur aktuellen Seite (funktioniert in Unterverzeichnissen/Backend gleichermaßen).
            // Nicht per String-Konkatenation mit location.origin verbinden, sondern über die
            // URL-API gegen die aktuelle Seite auflösen lassen.
            var query = '&land=' + encodeURIComponent(place.stateCode)
                + '&art=' + encodeURIComponent(species)
                + '&lat=' + encodeURIComponent(place.lat)
                + '&lon=' + encodeURIComponent(place.lon)
                + '&ort=' + encodeURIComponent(place.name)
                + '&morgens=' + (opts.morgens ? 1 : 0)
                + '&abends=' + (opts.abends ? 1 : 0)
                + '&vor=' + opts.vor
                + '&nach=' + opts.nach;

            var url = new URL(config.feedBase + query, window.location.href).href;
            var webcalUrl = url.replace(/^https?:/, 'webcal:');

            els.subscribeUrl.value = url;
            els.subscribeLink.href = webcalUrl;

            // Dokumentierte "von URL abonnieren"-Deeplinks der jeweiligen Anbieter - beide nehmen
            // die webcal://-URL als Parameterwert, daher hier zwingend encodeURIComponent (der
            // Feed-Link selbst enthält &/=, die sonst die äußere Query-String-Zerlegung stören).
            els.subscribeGoogle.href = 'https://calendar.google.com/calendar/render?cid=' + encodeURIComponent(webcalUrl);
            els.subscribeOutlook.href = 'https://outlook.office.com/calendar/0/addfromweb?url=' + encodeURIComponent(webcalUrl);
        }

        els.subscribeCopyBtn.addEventListener('click', function () {
            var value = els.subscribeUrl.value;
            if (!value) { return; }

            function showCopied() {
                els.subscribeCopyStatus.hidden = false;
                setTimeout(function () { els.subscribeCopyStatus.hidden = true; }, 2500);
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(showCopied, function () {
                    els.subscribeUrl.select();
                });
            } else {
                els.subscribeUrl.select();
                try {
                    if (document.execCommand('copy')) { showCopied(); }
                } catch (e) {
                    // Kopieren nicht verfügbar - Feld bleibt markiert zum manuellen Kopieren.
                }
            }
        });

        function slug(text) {
            return text
                .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
                .replace(/[^a-zA-Z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
        }

        // --- Einzelnen Tag prüfen (Modal) ---
        if (daycheckEls) {
            var todayInfo = todayBerlin();
            var todayStr = todayInfo.year + '-' + pad(todayInfo.month) + '-' + pad(todayInfo.day);
            var maxDateObj = new Date(Date.UTC(todayInfo.year + 5, todayInfo.month - 1, todayInfo.day));
            var maxStr = maxDateObj.getUTCFullYear() + '-' + pad(maxDateObj.getUTCMonth() + 1) + '-' + pad(maxDateObj.getUTCDate());
            daycheckEls.date.min = todayStr;
            daycheckEls.date.max = maxStr;
            daycheckEls.date.value = todayStr;

            daycheckEls.checkBtn.addEventListener('click', function () {
                if (!place) { return; }
                var raw = daycheckEls.date.value; // "YYYY-MM-DD"
                if (!raw) { return; }
                var parts = raw.split('-');
                var y = parseInt(parts[0], 10), m = parseInt(parts[1], 10), d = parseInt(parts[2], 10);
                if (!y || !m || !d) { return; }

                var species = currentSpecies();
                var opts = currentOptions();
                var periods = config.states[place.stateCode].species[species] || [];
                var info = computeDayInfo(y, m, d, periods, place.lat, place.lon, opts.morgens, opts.abends, opts.vor, opts.nach);

                daycheckEls.dateLabel.textContent = pad(d) + '.' + pad(m) + '.' + y;
                daycheckEls.speciesLabel.textContent = species;
                daycheckEls.stateLabel.textContent = config.states[place.stateCode].name;

                daycheckEls.inSeason.hidden = !info.inSeason;
                daycheckEls.outSeason.hidden = info.inSeason;

                if (info.inSeason) {
                    daycheckEls.sunrise.textContent = berlinTimeLabel(info.sunrise) + ' Uhr';
                    daycheckEls.sunset.textContent = berlinTimeLabel(info.sunset) + ' Uhr';

                    daycheckEls.morgenDt.hidden = !info.morgenStart;
                    daycheckEls.morgenDd.hidden = !info.morgenStart;
                    if (info.morgenStart) {
                        daycheckEls.morgenDd.textContent = berlinTimeLabel(info.morgenStart) + '–' + berlinTimeLabel(info.morgenEnd) + ' Uhr';
                    }

                    daycheckEls.abendDt.hidden = !info.abendStart;
                    daycheckEls.abendDd.hidden = !info.abendStart;
                    if (info.abendStart) {
                        daycheckEls.abendDd.textContent = berlinTimeLabel(info.abendStart) + '–' + berlinTimeLabel(info.abendEnd) + ' Uhr';
                    }
                }

                daycheckEls.result.hidden = false;
            });
        }

        updateInfoBox();
    }

    function init() {
        var containers = document.querySelectorAll('.bl-app');
        containers.forEach(function (c) {
            if (c.getAttribute('data-bl-initialized')) { return; }
            c.setAttribute('data-bl-initialized', '1');
            initApp(c);
        });
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
