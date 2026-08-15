<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Panel FTTH — Map — PROVISION NOC</title>
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        @property --ftth-angle {
            syntax: '<angle>';
            initial-value: 0deg;
            inherits: false;
        }
        html, body {
            height: 100%;
            margin: 0;
            overflow: hidden;
            background: #07111f;
        }
        #ftth-map {
            width: 100%;
            height: 100vh;
            background: #07111f;
        }
        /* Kursor map selalu terlihat (ring putih + titik hitam) di mode gelap & terang */
        .leaflet-container {
            cursor: url("data:image/svg+xml;utf8,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='20'%20height='20'%20viewBox='0%200%2020%2020'%3E%3Ccircle%20cx='10'%20cy='10'%20r='7'%20fill='none'%20stroke='white'%20stroke-width='2'/%3E%3Ccircle%20cx='10'%20cy='10'%20r='3'%20fill='black'/%3E%3C/svg%3E") 10 10, crosshair;
        }
        #ftth-map.ftth-map-locked.leaflet-container {
            cursor: default;
        }
        /* Filter tile per gaya map: dark = biru kehitaman (nolabels), light = jelas (nolabels), satelit = gaya Google Earth */
        #ftth-map.ftth-layer-dark .leaflet-tile-pane { filter: brightness(1.45) contrast(1.1); }
        #ftth-map.ftth-layer-light .leaflet-tile-pane { filter: contrast(1.15) saturate(1.1); }
        #ftth-map.ftth-layer-satelit .leaflet-tile-pane { filter: saturate(1.35) contrast(1.08) brightness(1.02); }
        .ftth-back-btn {
            --ftth-accent: #60a5fa;
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: rgba(7,17,31,0.85);
            border: 1px solid rgba(96,165,250,0.25);
            color: #fff;
            font-size: 12px;
            text-decoration: none;
            backdrop-filter: blur(8px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.35);
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .ftth-back-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            padding: 1px;
            background: conic-gradient(from var(--ftth-angle),
                transparent 0deg,
                #3b82f6 50deg,
                #60a5fa 90deg,
                transparent 150deg);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask-composite: exclude;
            pointer-events: none;
        }
        .ftth-back-btn i {
            color: var(--ftth-accent);
        }
        .ftth-back-btn:hover {
            background: color-mix(in srgb, var(--ftth-accent) 35%, rgba(7,17,31,0.85));
            border-color: var(--ftth-accent);
            color: #fff;
        }
        .ftth-back-btn:hover i {
            color: #fff;
        }
        .ftth-toolbar {
            position: fixed;
            top: 10px;
            left: 10px;
            right: 10px;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
            justify-content: flex-start;
        }
        .ftth-btn {
            --ftth-accent: #60a5fa;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 8px;
            background: rgba(7,17,31,0.85);
            border: 1px solid color-mix(in srgb, var(--ftth-accent) 55%, transparent);
            color: rgba(255,255,255,0.9);
            font-weight: 500;
            font-size: 11px;
            text-decoration: none;
            backdrop-filter: blur(8px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.35);
            transition: all 0.2s;
            cursor: pointer;
            white-space: nowrap;
        }
        .ftth-btn:hover {
            background: color-mix(in srgb, var(--ftth-accent) 35%, rgba(7,17,31,0.85));
            border-color: var(--ftth-accent);
            color: #fff;
        }
        .ftth-btn-ic {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 12px;
        }
        .ftth-btn-ic i {
            font-size: 11px;
            color: var(--ftth-accent);
        }
        .ftth-btn:hover .ftth-btn-ic i {
            color: #fff;
        }
        .ftth-search {
            --ftth-accent: #60a5fa;
            position: relative;
            display: flex;
            align-items: center;
            gap: 4px;
            width: 150px;
            padding: 4px 8px;
            border-radius: 8px;
            background: rgba(7,17,31,0.85);
            border: 1px solid color-mix(in srgb, var(--ftth-accent) 55%, transparent);
            color: rgba(255,255,255,0.6);
            backdrop-filter: blur(8px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.35);
            flex-shrink: 0;
            transition: all 0.2s;
        }
        .ftth-search-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 16px;
            height: 16px;
            border: none;
            background: transparent;
            color: #facc15;
            font-size: 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .ftth-search-btn:hover {
            background: rgba(250,204,21,0.18);
            color: #fff;
        }
        .ftth-search:focus-within .ftth-search-btn { color: #fff; }
        .ftth-search:focus-within {
            background: color-mix(in srgb, var(--ftth-accent) 20%, rgba(7,17,31,0.85));
            border-color: var(--ftth-accent);
        }
        .ftth-search input {
            flex: 1;
            min-width: 0;
            background: none;
            border: none;
            outline: none;
            color: #fff;
            font-size: 11px;
            font-family: inherit;
        }
        .ftth-search input::placeholder {
            color: rgba(255,255,255,0.4);
        }

        /* ── Autocomplete alamat (seperti Google Maps) ── */
        .ftth-search-suggest {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            z-index: 10001;
            display: none;
            flex-direction: column;
            background: rgba(7,17,31,0.95);
            border: 1px solid rgba(96,165,250,0.35);
            border-radius: 10px;
            backdrop-filter: blur(8px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            max-height: 280px;
            overflow-y: auto;
            padding: 4px;
        }
        .ftth-search-suggest.show { display: flex; }
        .ftth-search-suggest .ftth-sug-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            font-size: 11px;
            color: rgba(255,255,255,0.9);
            cursor: pointer;
            border-radius: 7px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .ftth-search-suggest .ftth-sug-item i { color: #60a5fa; font-size: 11px; flex-shrink: 0; }
        .ftth-search-suggest .ftth-sug-item .ftth-sug-label {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .ftth-search-suggest .ftth-sug-item .ftth-sug-badge {
            flex-shrink: 0;
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: .04em;
            padding: 2px 6px;
            border-radius: 5px;
            background: rgba(96,165,250,0.2);
            color: #93c5fd;
        }
        .ftth-search-suggest .ftth-sug-item:hover,
        .ftth-search-suggest .ftth-sug-item.active { background: rgba(96,165,250,0.25); }
        .ftth-search-suggest .ftth-sug-empty {
            padding: 10px 12px;
            font-size: 11px;
            color: rgba(255,255,255,0.5);
        }
        .ftth-icon-btn {
            --ftth-accent: #60a5fa;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 8px;
            background: rgba(7,17,31,0.85);
            border: 1px solid color-mix(in srgb, var(--ftth-accent) 55%, transparent);
            color: rgba(255,255,255,0.75);
            font-size: 12px;
            backdrop-filter: blur(8px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.35);
            transition: all 0.2s;
            cursor: pointer;
            flex-shrink: 0;
        }
        .ftth-icon-btn i {
            color: var(--ftth-accent);
        }
        .ftth-icon-btn:hover {
            background: color-mix(in srgb, var(--ftth-accent) 35%, rgba(7,17,31,0.85));
            border-color: var(--ftth-accent);
            color: #fff;
        }
        .ftth-icon-btn:hover i {
            color: #fff;
        }
        .ftth-icon-btn.active {
            border-color: var(--ftth-accent);
            background: color-mix(in srgb, var(--ftth-accent) 40%, rgba(7,17,31,0.85));
            color: #fff;
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--ftth-accent) 35%, transparent), 0 6px 20px rgba(0,0,0,0.35);
        }
        .ftth-icon-btn.active i {
            color: #fff;
        }
        .ftth-ac-mikrotik { --ftth-accent: #f97316; }
        .ftth-ac-olt { --ftth-accent: #a78bfa; }
        .ftth-ac-genieacs { --ftth-accent: #22d3ee; }
        .ftth-ac-backup { --ftth-accent: #fbbf24; }
        .ftth-ac-perangkat { --ftth-accent: #34d399; }
        .ftth-ac-onu { --ftth-accent: #f472b6; }
        .ftth-ac-queue { --ftth-accent: #2dd4bf; }
        .ftth-ac-lock { --ftth-accent: #f87171; margin-left: auto; }
        .ftth-ac-fullscreen { --ftth-accent: #c084fc; }
        .ftth-ac-measure { --ftth-accent: #4ade80; }
        .ftth-ac-back { --ftth-accent: #60a5fa; }
        .ftth-ac-search { --ftth-accent: #facc15; }
        .ftth-ac-calculator { --ftth-accent: #fb923c; }
        .ftth-ac-visibility { --ftth-accent: #06b6d4; }
        .ftth-ac-users { --ftth-accent: #e879f9; }
        .ftth-ac-anim { --ftth-accent: #a3e635; }
        .ftth-ac-notifications { --ftth-accent: #f43f5e; }

        /* ── FAB group (bottom-right) ── */
        .ftth-fab-group {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        .ftth-fab {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 24px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6, #ec4899, #f97316, #3b82f6);
            background-size: 300% 300%;
            animation: ftth-fab-grad 5s ease infinite, ftth-fab-glow 3s ease-in-out infinite;
            transition: transform .2s ease;
        }
        .ftth-fab:hover { transform: scale(1.08); }
        .ftth-fab:active { transform: scale(0.94); }
        @keyframes ftth-fab-grad {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        @keyframes ftth-fab-glow {
            0%, 100% { box-shadow: 0 10px 30px rgba(59,130,246,0.45); }
            50% { box-shadow: 0 10px 42px rgba(236,72,153,0.6); }
        }

        /* ── Map style trigger button (below FAB) ── */
        .ftth-fab-trigger {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 1px solid rgba(96,165,250,0.45);
            background: rgba(7,17,31,0.9);
            backdrop-filter: blur(8px);
            color: #60a5fa;
            font-size: 15px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px rgba(0,0,0,0.4);
            transition: all .25s;
        }
        .ftth-fab-trigger:hover {
            background: color-mix(in srgb, #60a5fa 30%, rgba(7,17,31,0.9));
            color: #fff;
            transform: scale(1.08);
        }
        .ftth-fab-trigger.active {
            background: rgba(59,130,246,0.35);
            border-color: #60a5fa;
            color: #fff;
            box-shadow: 0 0 0 3px rgba(96,165,250,0.25), 0 8px 24px rgba(0,0,0,0.4);
        }

        /* ── Map style switcher (sliding out left from behind the trigger) ── */
        .ftth-fab-trigger-wrap {
            position: relative;
            display: flex;
        }
        .ftth-map-styles {
            position: absolute;
            right: calc(100% + 12px);
            top: 50%;
            z-index: 10001;
            display: flex;
            flex-direction: row;
            gap: 8px;
            padding: 6px;
            background: rgba(7,17,31,0.92);
            border: 1px solid rgba(96,165,250,0.35);
            border-radius: 12px;
            backdrop-filter: blur(8px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.45);
            opacity: 0;
            transform: translateY(-50%) translateX(16px);
            pointer-events: none;
            transition: opacity .25s ease, transform .25s ease;
        }
        .ftth-map-styles.open {
            opacity: 1;
            transform: translateY(-50%) translateX(0);
            pointer-events: auto;
        }
        .ftth-style-btn {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 3px;
            width: 46px;
            height: 46px;
            border-radius: 9px;
            border: 1px solid rgba(96,165,250,0.3);
            background: rgba(7,17,31,0.9);
            color: rgba(255,255,255,0.85);
            font-size: 15px;
            cursor: pointer;
            transition: all .2s;
        }
        .ftth-style-btn small { font-size: 8.5px; letter-spacing: .02em; opacity: .8; }
        .ftth-style-btn:hover { background: rgba(96,165,250,0.25); color: #fff; }
        .ftth-style-btn.active { border-color: #60a5fa; background: rgba(96,165,250,0.3); color: #fff; }

        /* ── Penggaris Ukur: menu mode + panel hasil ── */
        .ftth-measure-wrap { position: relative; flex-shrink: 0; display: inline-flex; }
        .ftth-notif-wrap { position: relative; flex-shrink: 0; display: inline-flex; }
        .ftth-measure-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            z-index: 10002;
            display: flex;
            flex-direction: column;
            gap: 4px;
            width: 230px;
            padding: 6px;
            background: rgba(7,17,31,0.95);
            border: 1px solid rgba(74,222,128,0.35);
            border-radius: 12px;
            backdrop-filter: blur(8px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
            transition: opacity .2s ease, transform .2s ease;
        }
        .ftth-measure-menu.open { opacity: 1; transform: translateY(0); pointer-events: auto; }
        .ftth-measure-item {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 9px 10px;
            border-radius: 9px;
            border: 1px solid rgba(74,222,128,0.2);
            background: rgba(7,17,31,0.9);
            color: rgba(255,255,255,0.9);
            text-align: left;
            cursor: pointer;
            transition: all .2s;
        }
        .ftth-measure-item > i { font-size: 15px; color: #4ade80; width: 20px; text-align: center; }
        .ftth-measure-item > span { display: flex; flex-direction: column; }
        .ftth-measure-item strong { font-size: 11.5px; }
        .ftth-measure-item small { font-size: 9px; color: rgba(255,255,255,0.55); margin-top: 1px; line-height: 1.35; }
        .ftth-measure-item:hover { background: rgba(74,222,128,0.18); border-color: #4ade80; }
        .ftth-measure-item[data-mode="otdr"] { border-color: rgba(56,189,248,0.2); }
        .ftth-measure-item[data-mode="otdr"] > i { color: #38bdf8; }
        .ftth-measure-item[data-mode="otdr"]:hover { background: rgba(56,189,248,0.16); border-color: #38bdf8; }

        .ftth-notif-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            z-index: 10002;
            display: flex;
            flex-direction: column;
            gap: 4px;
            width: 240px;
            padding: 6px;
            background: rgba(7,17,31,0.95);
            border: 1px solid rgba(74,222,128,0.35);
            border-radius: 12px;
            backdrop-filter: blur(8px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
            transition: opacity .2s ease, transform .2s ease;
        }
        .ftth-notif-menu.open { opacity: 1; transform: translateY(0); pointer-events: auto; }
        .ftth-notif-item {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 9px 10px;
            border-radius: 9px;
            border: 1px solid rgba(74,222,128,0.2);
            background: rgba(7,17,31,0.9);
            color: rgba(255,255,255,0.9);
            text-align: left;
            cursor: pointer;
            transition: all .2s;
        }
        .ftth-notif-item > i { font-size: 15px; width: 20px; text-align: center; }
        .ftth-notif-item > span { display: flex; flex-direction: column; }
        .ftth-notif-item strong { font-size: 11.5px; }
        .ftth-notif-item small { font-size: 9px; color: rgba(255,255,255,0.55); margin-top: 1px; line-height: 1.35; }
        .ftth-notif-item.wa > i { color: #25d366; }
        .ftth-notif-item.wa:hover { background: rgba(37,211,102,0.16); border-color: #25d366; }
        .ftth-notif-item.tg > i { color: #38bdf8; }
        .ftth-notif-item.tg:hover { background: rgba(56,189,248,0.16); border-color: #38bdf8; }

        .ftth-measure-result {
            position: fixed;
            top: 52px;
            right: 10px;
            z-index: 10002;
            display: none;
            width: 252px;
            padding: 10px 12px;
            background: rgba(7,17,31,0.95);
            border: 1px solid rgba(74,222,128,0.35);
            border-radius: 12px;
            backdrop-filter: blur(8px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            color: #fff;
        }
        .ftth-measure-result.ftth-measure-otdr { border-color: rgba(56,189,248,0.35); }
        .ftth-measure-result-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
        .ftth-measure-result-title { font-size: 11.5px; font-weight: 700; }
        .ftth-measure-otdr .ftth-measure-result-title { color: #38bdf8; }
        .ftth-measure-x {
            width: 22px; height: 22px; border: none; border-radius: 6px;
            background: rgba(100,116,139,0.25); color: #cbd5e1; cursor: pointer; font-size: 11px;
        }
        .ftth-measure-x:hover { background: rgba(248,113,113,0.3); color: #fff; }
        .ftth-measure-result-body { display: flex; flex-direction: column; gap: 4px; font-size: 11px; }
        .fm-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .fm-row b { font-size: 12px; color: #4ade80; }
        .ftth-measure-otdr .fm-row b { color: #38bdf8; }
        .ftth-measure-result-hint { font-size: 9.5px; color: rgba(255,255,255,0.55); margin-top: 8px; line-height: 1.4; }
        .ftth-measure-result-actions { display: flex; gap: 6px; margin-top: 10px; }
        .ftth-measure-act {
            flex: 1;
            display: inline-flex; align-items: center; justify-content: center; gap: 5px;
            padding: 6px 8px; border-radius: 8px; border: 1px solid rgba(74,222,128,0.4);
            background: rgba(74,222,128,0.15); color: #d1fae5; font-size: 10px; font-weight: 600; cursor: pointer;
        }
        .ftth-measure-act:hover { background: rgba(74,222,128,0.3); }
        .ftth-measure-act.ftth-measure-otdr-act { border-color: rgba(56,189,248,0.4); background: rgba(56,189,248,0.15); color: #e0f2fe; }
        .ftth-measure-act.ftth-measure-otdr-act:hover { background: rgba(56,189,248,0.3); }

        .ftth-measure-point {
            width: 12px; height: 12px; border-radius: 50%;
            background: #4ade80; border: 2px solid #fff;
            box-shadow: 0 0 0 2px rgba(74,222,128,0.5), 0 2px 8px rgba(0,0,0,0.5);
        }
        .ftth-measure-point.otdr { background: #38bdf8; box-shadow: 0 0 0 2px rgba(56,189,248,0.5), 0 2px 8px rgba(0,0,0,0.5); }
        .ftth-measure-label {
            font-size: 10px; font-weight: 600; color: #fff; white-space: nowrap;
            padding: 1px 6px; border-radius: 6px;
            background: rgba(7,17,31,0.85); border: 1px solid rgba(74,222,128,0.45);
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
        }
        .ftth-measure-label.otdr { border-color: rgba(56,189,248,0.55); }

        /* ── Kalkulator Redaman: estimasi Output Power (drop) & Next ODP (pass) ── */
        .ftth-modal-card.ftth-calc-card { width: 344px; height: auto; }
        .ftth-calc-card .ftth-modal-head > i { color: #fb923c; }
        .ftth-calc-form { display: flex; flex-direction: column; gap: 9px; }
        .ftth-calc-field { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
        .ftth-calc-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 10.5px;
            color: rgba(255,255,255,0.65);
            letter-spacing: .03em;
        }
        .ftth-calc-label > i { font-size: 11px; width: 13px; text-align: center; }
        .ftth-calc-label .fa-bolt { color: #facc15; }
        .ftth-calc-label .fa-code-fork { color: #22d3ee; }
        .ftth-calc-label .fa-sitemap { color: #c084fc; }
        .ftth-calc-label .fa-ruler-horizontal { color: #38bdf8; }
        .ftth-calc-label .fa-link { color: #fb923c; }
        .ftth-calc-label .fa-plug { color: #f472b6; }
        .ftth-calc-hint {
            font-style: italic;
            font-size: 9px;
            color: rgba(255,255,255,0.45);
            line-height: 1.4;
            margin-top: 1px;
        }
        .ftth-calc-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .ftth-calc-card input[type="number"],
        .ftth-calc-card select {
            width: 100%;
            padding: 6px 9px;
            border-radius: 8px;
            border: 1px solid rgba(251,146,60,0.3);
            background: rgba(7,17,31,0.7);
            color: #fff;
            font-size: 12px;
            outline: none;
            box-sizing: border-box;
        }
        .ftth-calc-card input[type="number"]:focus,
        .ftth-calc-card select:focus { border-color: #fb923c; }
        .ftth-calc-card select option { background: #0b1524; color: #fff; }
        .ftth-calc-card input[type="number"] { color-scheme: dark; }
        .ftth-calc-inline { display: flex; align-items: center; gap: 6px; }
        .ftth-calc-inline > input { flex: 1; min-width: 0; }
        .ftth-calc-inline .ftth-calc-unit { flex: 0 0 62px; }
        .ftth-stepper { display: flex; align-items: center; gap: 5px; }
        .ftth-stepper button {
            width: 34px;
            height: 30px;
            flex-shrink: 0;
            border-radius: 8px;
            border: 1px solid rgba(251,146,60,0.35);
            background: rgba(251,146,60,0.12);
            color: #fdba74;
            font-size: 18px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
        }
        .ftth-stepper button:hover { background: rgba(251,146,60,0.28); }
        .ftth-stepper input {
            flex: 1;
            min-width: 0;
            text-align: center;
            -moz-appearance: textfield;
        }
        .ftth-stepper input::-webkit-outer-spin-button,
        .ftth-stepper input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        .ftth-calc-result {
            display: flex;
            flex-direction: column;
            gap: 5px;
            padding: 10px;
            margin-top: 2px;
            border: 1px solid rgba(251,146,60,0.3);
            border-radius: 12px;
            background: rgba(7,17,31,0.6);
        }
        .ftth-calc-result-title {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 10.5px;
            font-weight: 800;
            color: #fb923c;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .ftth-calc-result-title > i { color: #fdba74; }
        .ftth-calc-out {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 7px 9px;
            border-radius: 9px;
            background: rgba(251,146,60,0.08);
            border: 1px solid rgba(251,146,60,0.18);
        }
        .ftth-calc-out > span { display: inline-flex; align-items: center; gap: 7px; font-size: 11px; color: rgba(255,255,255,0.85); }
        .ftth-calc-out > span > i { color: #4ade80; font-size: 12px; }
        .ftth-calc-out b { font-size: 13px; color: #4ade80; font-variant-numeric: tabular-nums; }
        .ftth-calc-out.bad { background: rgba(248,113,113,0.08); border-color: rgba(248,113,113,0.3); }
        .ftth-calc-out.bad > span > i { color: #f87171; }
        .ftth-calc-out.bad b { color: #f87171; }
        .ftth-calc-note {
            font-style: italic;
            font-size: 9.5px;
            color: rgba(255,255,255,0.55);
            line-height: 1.5;
            margin-top: 2px;
        }

        /* ── Visibility card ── */
        .ftth-modal-card.ftth-vis-card { width: 344px; height: 600px; max-height: calc(100vh - 48px); }
        .ftth-vis-card .ftth-modal-head > i { color: #06b6d4; }
        .ftth-vis-card .ftth-modal-body {
            overflow-y: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .ftth-vis-card .ftth-modal-body::-webkit-scrollbar { display: none; }
        .ftth-vis-grid-col-5 {
            grid-auto-flow: column;
            grid-template-rows: repeat(5, auto);
            grid-template-columns: none;
        }
        .ftth-vis-section {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 9px 0 4px;
            font-size: 10px;
            font-weight: 800;
            color: #22d3ee;
            letter-spacing: .07em;
            text-transform: uppercase;
        }
        .ftth-vis-section > i { font-size: 11px; }
        .ftth-vis-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px 10px;
            align-content: start;
        }
        .ftth-vis-grid-col {
            grid-auto-flow: column;
            grid-template-rows: repeat(8, auto);
            grid-template-columns: none;
        }
        }
        .ftth-vis-check {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            color: rgba(255,255,255,0.85);
            cursor: pointer;
            user-select: none;
            min-width: 0;
        }
        .ftth-vis-check small { color: rgba(255,255,255,0.45); font-size: 9px; }
        .ftth-vis-check > span { display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ftth-vis-check input[type="checkbox"] {
            flex: 0 0 auto;
            width: 14px;
            height: 14px;
            accent-color: #06b6d4;
            cursor: pointer;
        }
        .ftth-vis-select {
            width: 100%;
            padding: 6px 9px;
            border-radius: 8px;
            border: 1px solid rgba(6,182,212,0.35);
            background: rgba(7,17,31,0.7);
            color: #fff;
            font-size: 12px;
            outline: none;
            box-sizing: border-box;
        }
        .ftth-vis-select:focus { border-color: #06b6d4; }
        .ftth-vis-select option { background: #0b1524; color: #fff; }
        .ftth-vis-inline { display: flex; gap: 14px; margin-top: 7px; }
        .ftth-vis-note {
            font-style: italic;
            font-size: 9.5px;
            color: rgba(255,255,255,0.45);
            line-height: 1.5;
            margin-top: 10px;
            border-top: 1px dashed rgba(6,182,212,0.3);
            padding-top: 8px;
        }
        .ftth-vis-note > i { color: #22d3ee; margin-right: 4px; }

        /* ── Animasi sembunyi tombol: kolaps dari kanan ke kiri ── */
        .ftth-toolbar .ftth-btn,
        .ftth-toolbar .ftth-icon-btn,
        .ftth-toolbar .ftth-measure-wrap {
            transition: all 0.25s ease;
        }
        .ftth-toolbar .ftth-vis-hidden {
            max-width: 0 !important;
            min-width: 0 !important;
            opacity: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            margin-right: -4px !important;
            border-left-width: 0 !important;
            border-right-width: 0 !important;
            overflow: hidden !important;
            pointer-events: none !important;
        }
        .ftth-fab-group {
            transition: opacity 0.45s ease, transform 0.45s ease;
        }
        .ftth-fab-group.ftth-vis-hidden {
            opacity: 0 !important;
            transform: translateX(-46px) !important;
            pointer-events: none !important;
        }

        /* ── Device status (bottom-left) ── */
        .ftth-status {
            position: fixed;
            left: 16px;
            bottom: 16px;
            z-index: 9999;
            display: flex;
            align-items: stretch;
            gap: 0;
            width: max-content;
            max-width: 42vw;
            overflow: hidden;
            background: rgba(7,17,31,0.85);
            border: 1px solid rgba(96,165,250,0.25);
            border-radius: 8px;
            backdrop-filter: blur(8px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.35);
            color: rgba(255,255,255,0.9);
            font-size: 9.5px;
            font-weight: 600;
            line-height: 1;
        }
        .ftth-status-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
            flex: 1 1 0;
            min-width: 0;
            padding: 4px 7px;
            white-space: nowrap;
            overflow: hidden;
        }
        .ftth-status-item + .ftth-status-item { border-left: 1px solid rgba(96,165,250,0.35); }
        .ftth-status-item b { font-weight: 700; }
        .ftth-status-dot { display: inline-block; width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .ftth-status-dot.online {
            background: #22c55e;
            animation: ftth-blink-smooth 1.8s ease-in-out infinite, ftth-ping-online 2.4s ease-out infinite;
        }
        .ftth-status-dot.offline {
            background: #ef4444;
            animation: ftth-blink-smooth 1.8s ease-in-out infinite .6s, ftth-ping-offline 2.4s ease-out infinite .6s;
        }
        @keyframes ftth-blink-smooth {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        @keyframes ftth-ping-online {
            0% { box-shadow: 0 0 0 0 rgba(34,197,94,0.55); }
            75%, 100% { box-shadow: 0 0 0 8px rgba(34,197,94,0); }
        }
        @keyframes ftth-ping-offline {
            0% { box-shadow: 0 0 0 0 rgba(239,68,68,0.55); }
            75%, 100% { box-shadow: 0 0 0 8px rgba(239,68,68,0); }
        }

        /* ── Copyright (bottom-center, card) ── */
        .ftth-copyright {
            position: fixed;
            left: 50%;
            transform: translateX(-50%);
            bottom: 16px;
            z-index: 9998;
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 4px 8px;
            background: rgba(7,17,31,0.85);
            border: 1px solid rgba(96,165,250,0.25);
            border-radius: 8px;
            backdrop-filter: blur(8px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.35);
            color: rgba(255,255,255,0.75);
            font-size: 9.5px;
            font-weight: 600;
            line-height: 1;
            letter-spacing: .03em;
            text-align: center;
            white-space: nowrap;
            pointer-events: none;
        }
        .ftth-copyright i {
            color: #60a5fa;
            font-size: 9.5px;
        }

        /* ── Full Screen: sembunyikan semua fitur, sisakan copyright + tombol ── */
        body.ftth-fs-active .ftth-toolbar > *:not(.ftth-fs-btn) { display: none !important; }
        body.ftth-fs-active .ftth-toolbar { justify-content: flex-end; }
        body.ftth-fs-active .ftth-fab-group { display: none !important; }
        body.ftth-fs-active .ftth-status { display: none !important; }
        body.ftth-fs-active .ftth-detail-card { display: none !important; }
        body.ftth-fs-active .ftth-measure-result { display: none !important; }
        body.ftth-fs-active .ftth-toast { display: none !important; }
        body.ftth-fs-active .ftth-modal-backdrop { display: none !important; }
        body.ftth-fs-active .ftth-fs-btn {
            position: fixed;
            top: 10px;
            right: 10px;
        }

        /* ── Search marker & toast ── */
        .ftth-search-marker {
            background: none;
            border: none;
        }
        .ftth-search-marker::after {
            content: '';
            position: absolute;
            left: 50%;
            top: 50%;
            width: 16px;
            height: 16px;
            margin: -8px 0 0 -8px;
            border-radius: 50%;
            background: rgba(244,63,94,0.35);
            animation: ftth-ping-search 1.6s ease-out infinite;
        }
        .ftth-search-marker i {
            position: relative;
            font-size: 28px;
            color: #f43f5e;
            filter: drop-shadow(0 3px 5px rgba(0,0,0,0.6));
            animation: ftth-marker-bounce 1.2s ease-in-out infinite;
        }
        @keyframes ftth-ping-search {
            0% { transform: scale(0.6); opacity: 1; }
            100% { transform: scale(3.2); opacity: 0; }
        }
        @keyframes ftth-marker-bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }
        .ftth-toast {
            position: fixed;
            top: 56px;
            left: 50%;
            transform: translateX(-50%) translateY(-8px);
            z-index: 10002;
            padding: 8px 16px;
            border-radius: 10px;
            background: rgba(7,17,31,0.92);
            border: 1px solid rgba(96,165,250,0.35);
            color: #fff;
            font-size: 12px;
            opacity: 0;
            pointer-events: none;
            transition: opacity .25s ease, transform .25s ease;
            max-width: 80vw;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .ftth-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .ftth-toast[data-type="error"] { border-color: rgba(244,63,94,0.5); }
        .ftth-toast[data-type="warn"] { border-color: rgba(251,191,36,0.5); }
        .ftth-toast[data-type="ok"] { border-color: rgba(34,197,94,0.5); }

        /* ── Sync Mikrotik modal ── */
        .ftth-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 10003;
            pointer-events: none;
        }
        .ftth-modal-backdrop[hidden] { display: none; }
        [hidden] { display: none !important; }
        .ftth-modal-card {
            position: fixed;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            width: 280px;
            height: 400px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            background: rgba(10,20,38,0.97);
            border: 1px solid rgba(96,165,250,0.35);
            border-radius: 14px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.6);
            color: #fff;
            font-size: 12px;
            overflow: hidden;
            pointer-events: auto;
        }
        .ftth-modal-head {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border-bottom: 1px solid rgba(96,165,250,0.2);
            font-weight: 700;
            font-size: 12px;
            flex-shrink: 0;
            cursor: grab;
            user-select: none;
        }
        .ftth-modal-head:active { cursor: grabbing; }
        .ftth-mt-status {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            max-width: 150px;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 600;
            background: rgba(148,163,184,0.2);
            color: rgba(255,255,255,0.8);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .ftth-mt-status.ok { background: rgba(34,197,94,0.18); color: #4ade80; }
        .ftth-mt-status.fail { background: rgba(244,63,94,0.18); color: #f87171; }
        .ftth-mt-status.info { background: rgba(96,165,250,0.18); color: #93c5fd; }
        .ftth-mt-status .ftth-spin { font-size: 10px; }
        .ftth-modal-head > i { color: #60a5fa; }
        .ftth-modal-close {
            margin-left: auto;
            background: none;
            border: none;
            color: rgba(255,255,255,0.6);
            font-size: 15px;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 6px;
        }
        .ftth-modal-close:hover { color: #fff; background: rgba(255,255,255,0.1); }
        .ftth-modal-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 10px 12px;
            overflow-y: auto;
        }
        .ftth-form { display: grid; gap: 4px; }
        .ftth-form label {
            font-size: 10.5px;
            color: rgba(255,255,255,0.65);
            letter-spacing: .03em;
        }
        .ftth-form input {
            width: 100%;
            padding: 6px 9px;
            border-radius: 8px;
            border: 1px solid rgba(96,165,250,0.3);
            background: rgba(7,17,31,0.7);
            color: #fff;
            font-size: 12px;
            outline: none;
            box-sizing: border-box;
        }
        .ftth-form input:focus { border-color: #60a5fa; }
        .ftth-form select {
            width: 100%;
            padding: 6px 9px;
            border-radius: 8px;
            border: 1px solid rgba(96,165,250,0.3);
            background: rgba(7,17,31,0.7);
            color: #fff;
            font-size: 12px;
            outline: none;
            box-sizing: border-box;
        }
        .ftth-form select:focus { border-color: #60a5fa; }
        .ftth-form select option { background: #0b1524; color: #fff; }
        .ftth-form-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px;
            margin-top: 10px;
        }
        .ftth-modal-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 7px 8px;
            border-radius: 8px;
            border: 1px solid rgba(96,165,250,0.35);
            background: rgba(37,99,235,0.35);
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
        }
        .ftth-modal-btn:hover { background: rgba(37,99,235,0.55); }
        .ftth-modal-btn.save {
            background: rgba(34,197,94,0.22);
            border-color: rgba(34,197,94,0.45);
        }
        .ftth-modal-btn.save:hover { background: rgba(34,197,94,0.42); }
        .ftth-modal-btn.syncall {
            grid-column: 1 / -1;
            background: rgba(251,191,36,0.2);
            border-color: rgba(251,191,36,0.45);
        }
        .ftth-modal-btn.syncall:hover { background: rgba(251,191,36,0.4); }
        .ftth-modal-btn[disabled] { opacity: .5; cursor: not-allowed; }
        .ftth-router-list { flex: 1; margin-top: 10px; display: grid; gap: 7px; align-content: start; }
        .ftth-router-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 8px 9px;
            border-radius: 10px;
            background: rgba(7,17,31,0.7);
            border: 1px solid rgba(96,165,250,0.2);
        }
        .ftth-router-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
        .ftth-router-line {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }
        .ftth-router-line .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .ftth-router-version { font-size: 11px; color: rgba(255,255,255,0.55); }
        .ftth-router-actions { display: inline-flex; gap: 6px; flex-shrink: 0; }
        .ftth-row-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 10px;
            border-radius: 7px;
            border: 1px solid rgba(96,165,250,0.3);
            background: rgba(37,99,235,0.25);
            color: #fff;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
        }
        .ftth-row-btn:hover { background: rgba(37,99,235,0.45); }
        .ftth-row-btn.sync {
            background: rgba(34,197,94,0.18);
            border-color: rgba(34,197,94,0.35);
        }
        .ftth-row-btn.sync:hover { background: rgba(34,197,94,0.35); }
        .ftth-row-btn.del { background: rgba(239,68,68,0.18); border-color: rgba(239,68,68,0.35); }
        .ftth-row-btn.del:hover { background: rgba(239,68,68,0.35); }
        .ftth-router-empty { font-size: 12px; color: rgba(255,255,255,0.45); text-align: center; padding: 8px 0; }
        @keyframes ftth-rotate { to { transform: rotate(360deg); } }
        .ftth-spin { display: inline-block; animation: ftth-rotate 1s linear infinite; }

        /* ── Sync OLT card: lebih tinggi & tanpa scrollbar ── */
        #ftthOltCard { height: 470px; }
        #ftthOltCard .ftth-modal-body,
        #ftthOltCard .ftth-router-list {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        #ftthOltCard .ftth-modal-body::-webkit-scrollbar,
        #ftthOltCard .ftth-router-list::-webkit-scrollbar { display: none; }

        /* ── Card GenieACS: ringkas, pas dengan isi ── */
        #ftthGenieacsCard { width: 340px; height: auto; }
        #ftthGenieacsCard .ftth-modal-body { overflow: visible; }
        #ftthGenieacsCard .ftth-router-list { overflow: visible; }
        #ftthGenieacsCard .ftth-form-actions { grid-template-columns: 1fr; }

        #ftthNotifWaCard, #ftthNotifTgCard { width: 320px; height: auto; }
        #ftthNotifWaCard .ftth-modal-body, #ftthNotifTgCard .ftth-modal-body { overflow: visible; gap: 8px; }
        #ftthNotifWaCard .ftth-form, #ftthNotifTgCard .ftth-form { margin-top: 6px; }
        #ftthNotifWaCard .ftth-modal-head > i, #ftthNotifTgCard .ftth-modal-head > i { color: #25d366; }
        #ftthNotifTgCard .ftth-modal-head > i { color: #38bdf8; }

        /* ── State auto-sync pada tombol toolbar ── */
        .ftth-btn.ftth-syncing { pointer-events: none; opacity: .85; }
        .ftth-btn.ftth-syncing .ftth-btn-ic { position: relative; }
        .ftth-btn.ftth-syncing .ftth-btn-ic::after {
            content: '';
            position: absolute;
            inset: -5px;
            border: 2px solid transparent;
            border-top-color: var(--ftth-accent);
            border-right-color: var(--ftth-accent);
            border-radius: 50%;
            animation: ftth-rotate .8s linear infinite;
        }

        /* ── Card Queue (PPPoE client) ── */
        #ftthQueueCard { width: 640px; height: 460px; }
        #ftthQueueCard .ftth-modal-body { padding: 0; gap: 0; }
        #ftthQueueCard .ftth-modal-body::-webkit-scrollbar { display: none; }
        .ftth-queue-toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            border-bottom: 1px solid rgba(96,165,250,0.2);
            flex-shrink: 0;
        }
        .ftth-queue-toolbar input {
            flex: 1;
            min-width: 0;
            padding: 6px 10px;
            border-radius: 8px;
            border: 1px solid rgba(96,165,250,0.3);
            background: rgba(7,17,31,0.7);
            color: #fff;
            font-size: 12px;
            outline: none;
            box-sizing: border-box;
        }
        .ftth-queue-toolbar input:focus { border-color: #60a5fa; }
        .ftth-queue-toolbar .ftth-modal-btn { padding: 6px 12px; }
        .ftth-queue-table-wrap {
            flex: 1;
            overflow-y: auto;
            padding: 10px 12px;
            scrollbar-width: thin;
            scrollbar-color: rgba(96,165,250,0.4) transparent;
        }
        .ftth-queue-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11.5px;
        }
        .ftth-queue-table th {
            position: sticky;
            top: 0;
            z-index: 1;
            text-align: left;
            padding: 7px 8px;
            background: rgba(10,20,38,0.98);
            color: rgba(255,255,255,0.6);
            font-weight: 600;
            font-size: 10.5px;
            letter-spacing: .04em;
            text-transform: uppercase;
            border-bottom: 1px solid rgba(96,165,250,0.25);
        }
        .ftth-queue-table td {
            padding: 7px 8px;
            border-bottom: 1px solid rgba(96,165,250,0.12);
            vertical-align: top;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 180px;
        }
        .ftth-queue-table tbody tr:hover { background: rgba(96,165,250,0.12); }
        .ftth-queue-table .ftth-q-user { font-weight: 700; color: #93c5fd; }
        .ftth-queue-table .ftth-q-router { color: rgba(255,255,255,0.65); }
        .ftth-queue-table .ftth-q-actions {
            text-align: center;
            white-space: nowrap !important;
            overflow: visible !important;
            max-width: none !important;
        }
        .ftth-row-btn.add {
            background: rgba(45,212,191,0.16);
            border-color: rgba(45,212,191,0.4);
            color: #5eead4;
            padding: 5px 12px;
        }
        .ftth-row-btn.add:hover { background: rgba(45,212,191,0.32); }
        .ftth-queue-table td.ftth-q-comment { color: rgba(255,255,255,0.6); max-width: 140px; }

        /* ── Card Backup & Restore ── */
        #ftthBackupCard { width: 430px; height: auto; }
        #ftthBackupCard .ftth-modal-body { overflow: visible; gap: 8px; }
        .ftth-bs {
            border-radius: 10px;
            border: 1px solid;
            padding: 9px 11px;
            display: grid;
            gap: 8px;
        }
        .ftth-bs-1 { background: rgba(37,99,235,0.10); border-color: rgba(59,130,246,0.4); }
        .ftth-bs-2 { background: rgba(34,197,94,0.08); border-color: rgba(34,197,94,0.4); }
        .ftth-bs-3 { background: rgba(249,115,22,0.08); border-color: rgba(249,115,22,0.4); }
        .ftth-bs-4 { background: rgba(168,85,247,0.08); border-color: rgba(168,85,247,0.4); }
        .ftth-bs-head {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 11.5px;
            font-weight: 700;
        }
        .ftth-bs-1 .ftth-bs-head { color: #93c5fd; }
        .ftth-bs-2 .ftth-bs-head { color: #86efac; }
        .ftth-bs-3 .ftth-bs-head { color: #fdba74; }
        .ftth-bs-4 .ftth-bs-head { color: #d8b4fe; }
        .ftth-bs-head i { font-size: 12px; }
        .ftth-bs-tag {
            font-size: 9px;
            font-weight: 600;
            padding: 1px 7px;
            border-radius: 999px;
            background: rgba(251,191,36,0.2);
            color: #fcd34d;
            margin-left: auto;
            white-space: nowrap;
        }
        .ftth-bs-form { grid-template-columns: 1fr; gap: 7px; align-items: end; }
        .ftth-bs-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .ftth-backup-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            border: 1px solid;
            font-size: 11px;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
        }
        .ftth-backup-btn[disabled] { opacity: .5; cursor: not-allowed; }
        .ftth-bu-1 { background: rgba(59,130,246,0.18); border-color: rgba(59,130,246,0.65); color: #93c5fd; }
        .ftth-bu-1:hover { background: rgba(59,130,246,0.42); }
        .ftth-bu-2 { background: rgba(6,182,212,0.16); border-color: rgba(6,182,212,0.65); color: #67e8f9; }
        .ftth-bu-2:hover { background: rgba(6,182,212,0.4); }
        .ftth-bu-3 { background: rgba(34,197,94,0.15); border-color: rgba(34,197,94,0.65); color: #86efac; }
        .ftth-bu-3:hover { background: rgba(34,197,94,0.38); }
        .ftth-bu-4 { background: rgba(20,184,166,0.15); border-color: rgba(20,184,166,0.65); color: #5eead4; }
        .ftth-bu-4:hover { background: rgba(20,184,166,0.38); }
        .ftth-bu-5 { background: rgba(245,158,11,0.15); border-color: rgba(245,158,11,0.65); color: #fcd34d; }
        .ftth-bu-5:hover { background: rgba(245,158,11,0.4); }
        .ftth-bu-6 { background: rgba(239,68,68,0.15); border-color: rgba(239,68,68,0.65); color: #fca5a5; }
        .ftth-bu-6:hover { background: rgba(239,68,68,0.4); }
        .ftth-bu-7 { background: rgba(139,92,246,0.16); border-color: rgba(139,92,246,0.65); color: #c4b5fd; }
        .ftth-bu-7:hover { background: rgba(139,92,246,0.4); }
        .ftth-bu-8 { background: rgba(217,70,239,0.15); border-color: rgba(217,70,239,0.65); color: #f0abfc; }
        .ftth-bu-8:hover { background: rgba(217,70,239,0.4); }

        /* ── Card Tambah Perangkat ── */
        .ftth-device-card { width: 360px; height: auto; max-height: 90vh; display: flex; flex-direction: column; }
        .ftth-device-card .ftth-modal-head { flex: 0 0 auto; }
        .ftth-device-card .ftth-modal-body { flex: 1 1 auto; min-height: 0; overflow: auto; }
        #ftthAddDeviceCard { left: 14px; top: 50%; transform: translateY(-50%); width: 330px; }
        .ftth-device-status {
            font-size: 11px; color: #94a3b8; margin-left: 10px;
            background: rgba(15,23,42,0.6); border: 1px solid rgba(51,65,85,0.6);
            border-radius: 99px; padding: 2px 10px; white-space: nowrap;
        }
        .ftth-df { display: flex; flex-direction: column; gap: 5px; margin-bottom: 10px; }
        .ftth-df > label { font-size: 10.5px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; }
        #ftthDeviceFields { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 10px; }
        #ftthDeviceFields .ftth-df { margin-bottom: 0; }
        #ftthDeviceFields .ftth-df:last-child:nth-child(odd) { grid-column: 1 / -1; }
        .ftth-df-hint { grid-column: 1 / -1; font-size: 11px; color: #64748b; font-style: italic; }
        .ftth-df-select { margin-bottom: 12px; }
        .ftth-df > input,
        .ftth-df > select {
            width: 100%;
            box-sizing: border-box;
            background: rgba(15,23,42,0.75);
            border: 1px solid rgba(51,65,85,0.85);
            border-radius: 8px;
            color: #e2e8f0;
            padding: 8px 10px;
            font-size: 12px;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .ftth-df > input:focus, .ftth-df > select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.18);
        }
        .ftth-df > select { cursor: pointer; }
        .ftth-device-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 10px;
            border-top: 1px dashed rgba(51,65,85,0.7);
            padding-top: 10px;
            margin-top: 12px;
        }
        .ftth-device-grid .ftth-df { margin-bottom: 0; }
        .ftth-df-wide { grid-column: 1 / -1; }
        .ftth-core-chk { margin: 2px 0 12px; padding-top: 8px; border-top: 1px dashed rgba(51,65,85,0.7); }
        .ftth-core-chk-label {
            display: flex; align-items: center; gap: 8px;
            font-size: 12px; font-weight: 700; color: #e2e8f0; cursor: pointer;
            user-select: none;
        }
        .ftth-core-chk-label input[type="checkbox"] { display: none; }
        .ftth-core-chk-box {
            width: 18px; height: 18px; border-radius: 5px;
            border: 1px solid rgba(96,165,250,0.5);
            background: rgba(15,23,42,0.7);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 10px;
            transition: all .15s;
            flex: 0 0 auto;
        }
        .ftth-core-chk-label input:checked + .ftth-core-chk-box {
            background: #3b82f6; border-color: #60a5fa;
        }
        .ftth-btn-batal { background: rgba(51,65,85,0.5); border-color: rgba(100,116,139,0.4); }
        .ftth-btn-batal:hover { background: rgba(51,65,85,0.85); }
        .ftth-btn-primary {
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 700;
            font-size: 12px;
            padding: 10px 14px;
            cursor: pointer;
            transition: transform .15s, filter .15s;
        }
        .ftth-btn-primary:hover { filter: brightness(1.12); }
        .ftth-btn-primary:active { transform: scale(0.98); }
        .ftth-btn-primary[disabled] { opacity: .55; cursor: not-allowed; filter: none; }
        .ftth-btn-full { width: 100%; }

        /* ── Pin tagging lokasi (draggable di peta) ── */
        .ftth-tag-icon { background: none; border: none; }
        .ftth-tag-icon i { font-size: 27px; color: #f87171; text-shadow: 0 3px 9px rgba(0,0,0,0.7); }

        /* ── Marker peta: icon perangkat + label (nama & lokasi) ── */
        .ftth-marker-label { background: none; border: none; text-align: center; }
        .ftth-onu-label {
            background: rgba(7,17,31,0.92);
            border: 1px solid rgba(6,182,212,0.45);
            color: #e2e8f0;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
            white-space: nowrap;
        }
        .ftth-onu-label::before { display: none; }
        .ftth-ic {
            display: flex; flex-direction: column; align-items: center;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.65));
        }
        .ftth-ic-i {
            width: 26px; height: 26px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 8px;
            background: rgba(7,17,31,0.92);
            border: 2px solid rgba(51,65,85,0.9);
            box-shadow: 0 0 0 1px rgba(255,255,255,0.2);
        }
        .ftth-ic-i i { font-size: 13px; color: #e2e8f0; }
        .ftth-ic.blink-on .ftth-ic-i { border-color: rgba(34,197,94,0.9); }
        .ftth-ic.blink-on .ftth-ic-i i { color: #22c55e; animation: ftth-blink-on 1.1s ease-in-out infinite; }
        .ftth-ic.blink-off .ftth-ic-i { border-color: rgba(239,68,68,0.9); }
        .ftth-ic.blink-off .ftth-ic-i i { color: #ef4444; animation: ftth-blink-off 1.1s ease-in-out infinite; }
        @keyframes ftth-blink-on {
            0%, 100% { opacity: 1; text-shadow: 0 0 14px #22c55e; transform: scale(1); }
            50% { opacity: .25; text-shadow: none; transform: scale(.86); }
        }
        @keyframes ftth-blink-off {
            0%, 100% { opacity: 1; text-shadow: 0 0 14px #ef4444; transform: scale(1); }
            50% { opacity: .28; text-shadow: none; transform: scale(.86); }
        }
        .ftth-cable {
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }
        .ftth-cable-flow {
            stroke-dasharray: 7 6;
            animation: ftth-cable-flow 0.9s linear infinite;
        }
        .ftth-cable-stop {
            stroke-dasharray: 5 4;
            animation: none;
        }
        @keyframes ftth-cable-flow {
            to { stroke-dashoffset: -13; }
        }

        /* ── Master toggle animasi: matikan semua animasi peta sekaligus ── */
        body.ftth-anim-off * {
            animation: none !important;
        }

        /* ── List Perangkat ── */
        .ftth-devices-list-card { width: 440px; }
        .ftth-device-list { display: flex; flex-direction: column; gap: 8px; }
        .ftth-device-row {
            display: flex; align-items: center; gap: 10px;
            background: rgba(15,23,42,0.6);
            border: 1px solid rgba(51,65,85,0.7);
            border-radius: 10px;
            padding: 9px 12px;
        }
        .ftth-device-type-badge {
            flex: 0 0 auto;
            font-size: 10px; font-weight: 800;
            border-radius: 6px; padding: 4px 8px;
            letter-spacing: .04em;
            color: #020617;
        }
        .ftth-device-row-main { flex: 1 1 auto; min-width: 0; }
        .ftth-device-row-name { font-size: 12.5px; font-weight: 700; color: #f1f5f9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ftth-device-row-sub { font-size: 11px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ftth-device-row-del {
            flex: 0 0 auto;
            width: 28px; height: 28px;
            border-radius: 7px;
            border: 1px solid rgba(239,68,68,0.5);
            background: rgba(239,68,68,0.12);
            color: #fca5a5;
            cursor: pointer;
            transition: all .15s;
        }
        .ftth-device-row-del:hover { background: rgba(239,68,68,0.35); color: #fff; }
        .ftth-device-row-status {
            flex: 0 0 auto;
            display: flex; align-items: center; gap: 4px;
            font-size: 9.5px; font-weight: 800; letter-spacing: .03em;
            padding: 4px 8px;
            border-radius: 99px;
            border: 1px solid rgba(51,65,85,0.8);
            background: rgba(15,23,42,0.7);
            color: #94a3b8;
            cursor: pointer;
            transition: all .15s;
            white-space: nowrap;
        }
        .ftth-device-row-status.st-online { color: #4ade80; border-color: rgba(34,197,94,0.55); background: rgba(34,197,94,0.12); }
        .ftth-device-row-status.st-offline { color: #f87171; border-color: rgba(239,68,68,0.55); background: rgba(239,68,68,0.12); }
        .ftth-device-row-status:hover { filter: brightness(1.2); }
        .ftth-device-empty { text-align: center; color: #64748b; font-size: 12px; padding: 26px 10px; }

        /* ── Card info perangkat (klik marker) ── */
        .ftth-detail-card {
            position: fixed;
            right: 16px;
            bottom: 76px;
            z-index: 9990;
            width: 300px;
            max-height: 60vh;
            display: flex;
            flex-direction: column;
            background: rgba(10,20,38,0.96);
            border: 1px solid rgba(96,165,250,0.35);
            border-radius: 12px;
            box-shadow: 0 16px 44px rgba(0,0,0,0.55);
            color: #fff;
            font-size: 12px;
            overflow: hidden;
            backdrop-filter: blur(8px);
        }
        .ftth-detail-head {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 10px;
            border-bottom: 1px solid rgba(96,165,250,0.2);
            flex-shrink: 0;
        }
        .ftth-detail-name {
            flex: 1 1 auto;
            min-width: 0;
            font-weight: 700;
            font-size: 12.5px;
            color: #f1f5f9;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .ftth-detail-body {
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 7px;
            overflow-y: auto;
        }
        .ftth-detail-row {
            display: flex;
            align-items: flex-start;
            gap: 7px;
            color: #cbd5e1;
            font-size: 11px;
        }
        .ftth-detail-row i { color: #60a5fa; margin-top: 2px; }
        .ftth-detail-attrs { display: grid; gap: 4px; }
        .ftth-detail-attr {
            display: flex;
            gap: 8px;
            font-size: 11px;
        }
        .ftth-detail-attr b { color: #94a3b8; font-weight: 600; flex: 0 0 auto; }
        .ftth-detail-attr span { color: #e2e8f0; word-break: break-word; }
        .ftth-detail-notes {
            display: flex;
            align-items: flex-start;
            gap: 7px;
            color: #fbbf24;
            font-size: 11px;
        }
        .ftth-detail-notes i { margin-top: 2px; }
        .ftth-detail-actions {
            display: flex;
            gap: 7px;
            margin-top: 4px;
        }
        .ftth-detail-del {
            flex: 0 0 auto;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            border: 1px solid rgba(239,68,68,0.5);
            background: rgba(239,68,68,0.12);
            color: #fca5a5;
            cursor: pointer;
            transition: all .15s;
        }
        .ftth-detail-del:hover { background: rgba(239,68,68,0.35); color: #fff; }
        .ftth-device-row-status.st-other { color: #fbbf24; border-color: rgba(251,191,36,0.55); background: rgba(251,191,36,0.12); }
        .ftth-detail-actions { flex-wrap: wrap; }
        .ftth-detail-live {
            display: flex;
            flex-direction: column;
            gap: 4px;
            border-top: 1px dashed rgba(96,165,250,0.25);
            padding-top: 7px;
        }
        .ftth-detail-live-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: #60a5fa;
            font-weight: 600;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .ftth-detail-attr.has-copy { display: flex; align-items: center; gap: 6px; min-width: 0; }
        .ftth-detail-attr.has-copy span { flex: 1 1 auto; }
        .ftth-copy-btn {
            flex: 0 0 auto;
            width: 22px;
            height: 22px;
            border: 1px solid rgba(96,165,250,0.35);
            background: rgba(96,165,250,0.08);
            color: #93c5fd;
            border-radius: 6px;
            cursor: pointer;
            font-size: 10px;
            transition: all .15s;
        }
        .ftth-copy-btn:hover { background: rgba(96,165,250,0.25); color: #fff; }
        .ftth-cust-btn {
            flex: 0 0 auto;
            height: 30px;
            padding: 0 10px;
            border-radius: 8px;
            border: 1px solid rgba(96,165,250,0.4);
            background: rgba(96,165,250,0.1);
            color: #bfdbfe;
            cursor: pointer;
            font-size: 11px;
            transition: all .15s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .ftth-cust-btn:hover { background: rgba(96,165,250,0.25); color: #fff; }
        .ftth-cust-btn.danger { border-color: rgba(239,68,68,0.5); background: rgba(239,68,68,0.1); color: #fca5a5; }
        .ftth-cust-btn.danger:hover { background: rgba(239,68,68,0.3); color: #fff; }
        .ftth-cust-btn.whatsapp { border-color: rgba(34,197,94,0.5); background: rgba(34,197,94,0.1); color: #86efac; }
        .ftth-cust-btn.whatsapp:hover { background: rgba(34,197,94,0.3); color: #fff; }
        .ftth-cust-btn[disabled] { opacity: .5; cursor: not-allowed; }
        .ftth-detail-log {
            display: flex;
            flex-direction: column;
            gap: 5px;
            border-top: 1px dashed rgba(96,165,250,0.25);
            padding-top: 7px;
            font-size: 11px;
        }
        .ftth-log-row {
            padding: 6px 8px;
            border-radius: 8px;
            background: rgba(15,23,42,0.7);
            border: 1px solid rgba(51,65,85,0.6);
            color: #cbd5e1;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .ftth-log-row.ok { border-color: rgba(34,197,94,0.4); color: #86efac; }
        .ftth-log-row.err { border-color: rgba(239,68,68,0.4); color: #fca5a5; }
        .ftth-log-row.info { border-color: rgba(96,165,250,0.4); color: #bfdbfe; }

        /* ── Tabel ONU ── */
        .ftth-onu-table-card { width: 600px; }
        .ftth-onu-table-wrap { overflow: auto; border: 1px solid rgba(51,65,85,0.7); border-radius: 10px; }
        .ftth-onu-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .ftth-onu-table th {
            position: sticky; top: 0;
            background: rgba(10,18,32,0.97);
            color: #94a3b8; font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em;
            padding: 8px 10px; text-align: left;
            border-bottom: 1px solid rgba(51,65,85,0.8);
            white-space: nowrap;
        }
        .ftth-onu-table td { padding: 7px 10px; color: #cbd5e1; border-bottom: 1px solid rgba(51,65,85,0.45); white-space: nowrap; }
        .ftth-onu-table tbody tr:hover td { background: rgba(59,130,246,0.06); }
        .ftth-onu-table tbody tr:last-child td { border-bottom: none; }
        .ftth-bu-8:hover { background: rgba(217,70,239,0.38); }
        .ftth-file-hidden { display: none; }
    </style>
</head>
<body>
    <div id="ftth-map"></div>

    <div class="ftth-toolbar">
        <a href="{{ route('noc.dashboard') }}" class="ftth-back-btn ftth-ac-back" title="Kembali ke NOC Dashboard">
            <i class="fa-solid fa-arrow-left"></i>
        </a>

        <button type="button" class="ftth-btn ftth-ac-mikrotik" data-feature="sync-mikrotik" title="Sync Mikrotik" onclick="ftthOpenMikrotik()">
            <span class="ftth-btn-ic"><i class="fa-solid fa-server"></i></span>
            Sync Mikrotik
        </button>

        <button type="button" class="ftth-btn ftth-ac-olt" data-feature="sync-olt" title="Sync OLT" onclick="ftthOpenOlt()">
            <span class="ftth-btn-ic"><i class="fa-solid fa-tower-broadcast"></i></span>
            Sync OLT
        </button>

        <button type="button" class="ftth-btn ftth-ac-genieacs" data-feature="sync-genieacs" title="Sync GenieACS" onclick="ftthOpenGenieacs()">
            <span class="ftth-btn-ic"><i class="fa-solid fa-satellite-dish"></i></span>
            Sync GenieACS
        </button>

        <button type="button" class="ftth-btn ftth-ac-backup" data-feature="backup-restore" title="Backup & Restore" onclick="ftthOpenBackup()">
            <span class="ftth-btn-ic"><i class="fa-solid fa-database"></i></span>
            Backup &amp; Restore
        </button>

        <button type="button" class="ftth-btn ftth-ac-perangkat" data-feature="perangkat" title="Perangkat" onclick="ftthOpenDevices()">
            <span class="ftth-btn-ic"><i class="fa-solid fa-hdd"></i></span>
            Perangkat
        </button>

        <button type="button" class="ftth-btn ftth-ac-onu" data-feature="tabel-onu" title="Tabel ONU" onclick="ftthOpenOnuTable()">
            <span class="ftth-btn-ic"><i class="fa-solid fa-table-list"></i></span>
            Tabel ONU
        </button>

        <button type="button" class="ftth-btn ftth-ac-queue" data-feature="queue" title="Queue" onclick="ftthOpenQueue()">
            <span class="ftth-btn-ic"><i class="fa-solid fa-chart-simple"></i></span>
            Queue
        </button>

        <div class="ftth-search ftth-ac-search">
            <button type="button" class="ftth-search-btn" title="Cari" onclick="ftthSearch()">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            <input type="text" data-feature="search" id="ftthSearchInput" placeholder="Cari Lat, Lang, atau nama..." autocomplete="off">
            <div class="ftth-search-suggest" id="ftthSearchSuggest"></div>
        </div>

        <button type="button" class="ftth-icon-btn ftth-ac-lock" data-feature="lock" id="ftthLockBtn" title="Kunci Peta" onclick="ftthToggleLock()">
            <i class="fa-solid fa-lock" id="ftthLockIcon"></i>
        </button>

        <span class="ftth-measure-wrap">
            <button type="button" class="ftth-icon-btn ftth-ac-measure" id="ftthMeasureBtn" data-feature="measure" title="Penggaris Ukur" onclick="ftthToggleMeasureMenu()">
                <i class="fa-solid fa-ruler-combined"></i>
            </button>
            <div class="ftth-measure-menu" id="ftthMeasureMenu">
                <button type="button" class="ftth-measure-item" data-mode="ukur" onclick="ftthMeasureStart('ukur')">
                    <i class="fa-solid fa-ruler"></i>
                    <span><strong>Mode Ukur</strong><small>Ukur jarak langsung antar titik di peta</small></span>
                </button>
                <button type="button" class="ftth-measure-item" data-mode="otdr" onclick="ftthMeasureStart('otdr')">
                    <i class="fa-solid fa-wave-square"></i>
                    <span><strong>Mode OTDR</strong><small>Estimasi panjang kabel &amp; redaman fiber</small></span>
                </button>
            </div>
        </span>

        <button type="button" class="ftth-icon-btn ftth-ac-fullscreen ftth-fs-btn" id="ftthFullscreenBtn" data-feature="fullscreen" title="Full Screen" onclick="ftthToggleFullscreen()">
            <i class="fa-solid fa-expand" id="ftthFullscreenIcon"></i>
        </button>

        <button type="button" class="ftth-icon-btn ftth-ac-calculator" data-feature="calculator" title="Kalkulator Redaman" onclick="ftthOpenCalc()">
            <i class="fa-solid fa-calculator"></i>
        </button>

        <button type="button" class="ftth-icon-btn ftth-ac-visibility" data-feature="visibility" title="Visibility" onclick="ftthOpenVis()">
            <i class="fa-solid fa-eye"></i>
        </button>

        <button type="button" class="ftth-icon-btn ftth-ac-users" data-feature="users" title="Users">
            <i class="fa-solid fa-users"></i>
        </button>

        <button type="button" class="ftth-icon-btn ftth-ac-anim active" data-feature="anim" id="ftthAnimBtn" title="Matikan Animasi" onclick="ftthToggleAnim()">
            <i class="fa-solid fa-circle-play" id="ftthAnimIcon"></i>
        </button>

        <span class="ftth-notif-wrap">
            <button type="button" class="ftth-icon-btn ftth-ac-notifications" id="ftthNotifBtn" data-feature="notifications" title="Notifications" onclick="ftthToggleNotifMenu()">
                <i class="fa-solid fa-bell"></i>
            </button>
            <div class="ftth-notif-menu" id="ftthNotifMenu">
                <button type="button" class="ftth-notif-item wa" onclick="ftthOpenNotifWa()">
                    <i class="fa-brands fa-whatsapp"></i>
                    <span><strong>Pengaturan WhatsApp</strong><small>URL API, key &amp; nomor pengiriman</small></span>
                </button>
                <button type="button" class="ftth-notif-item tg" onclick="ftthOpenNotifTg()">
                    <i class="fa-brands fa-telegram"></i>
                    <span><strong>Pengaturan Telegram</strong><small>Bot token &amp; chat ID tujuan</small></span>
                </button>
            </div>
        </span>
    </div>

    <div class="ftth-measure-result" id="ftthMeasureResult">
        <div class="ftth-measure-result-head">
            <span class="ftth-measure-result-title" id="ftthMeasureTitle">Pengukuran Jarak</span>
            <button type="button" class="ftth-measure-x" title="Tutup" onclick="ftthMeasureClose()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="ftth-measure-result-body" id="ftthMeasureBody"></div>
        <div class="ftth-measure-result-hint" id="ftthMeasureHint">Klik titik di peta untuk mulai mengukur. Klik kanan / Selesai untuk mengakhiri.</div>
        <div class="ftth-measure-result-actions">
            <button type="button" class="ftth-measure-act" id="ftthMeasureSelesaiBtn" onclick="ftthMeasureSelesai()"><i class="fa-solid fa-check"></i> Selesai</button>
            <button type="button" class="ftth-measure-act" onclick="ftthMeasureHapus()"><i class="fa-solid fa-trash-can"></i> Hapus</button>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthCalcBackdrop" hidden>
        <div class="ftth-modal-card ftth-calc-card" id="ftthCalcCard">
            <div class="ftth-modal-head">
                <i class="fa-solid fa-bolt"></i>
                Kalkulator Redaman
                <button type="button" class="ftth-modal-close" onclick="ftthCloseCalc()" title="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-calc-form">
                    <div class="ftth-calc-field">
                        <span class="ftth-calc-label"><i class="fa-solid fa-bolt"></i> Input Power OLT/ODC (dBm)</span>
                        <div class="ftth-stepper">
                            <button type="button" onclick="ftthCalcFlipSign()" title="Ganti tanda + / -">&#177;</button>
                            <input type="number" id="fcInputPower" step="0.5" value="3" placeholder="cth: 3 atau -5.5" autocomplete="off">
                        </div>
                        <div class="ftth-calc-hint">Isi power input dalam dBm. Nilai minus (-) artinya power sudah melemah (mis. output ODC). Tombol &#177; untuk ganti tanda.</div>
                    </div>
                    <div class="ftth-calc-row">
                        <div class="ftth-calc-field">
                            <span class="ftth-calc-label"><i class="fa-solid fa-code-fork"></i> Splitter Rasio</span>
                            <select id="fcRatio">
                                <option value="">Tanpa Rasio</option>
                                <option value="1:99">1:99</option>
                                <option value="2:98">2:98</option>
                                <option value="3:97">3:97</option>
                                <option value="4:96">4:96</option>
                                <option value="5:95">5:95</option>
                                <option value="10:90">10:90</option>
                                <option value="15:85">15:85</option>
                                <option value="20:80">20:80</option>
                                <option value="25:75">25:75</option>
                                <option value="30:70">30:70</option>
                                <option value="35:65">35:65</option>
                                <option value="40:60">40:60</option>
                                <option value="45:55">45:55</option>
                                <option value="50:50">50:50</option>
                            </select>
                        </div>
                        <div class="ftth-calc-field">
                            <span class="ftth-calc-label"><i class="fa-solid fa-sitemap"></i> Splitter PLC</span>
                            <select id="fcPlc">
                                <option value="0">Tanpa PLC</option>
                                <option value="2">1:2</option>
                                <option value="4">1:4</option>
                                <option value="8">1:8</option>
                                <option value="16">1:16</option>
                                <option value="32">1:32</option>
                                <option value="64">1:64</option>
                            </select>
                        </div>
                    </div>
                    <div class="ftth-calc-row">
                        <div class="ftth-calc-field">
                            <span class="ftth-calc-label"><i class="fa-solid fa-ruler-horizontal"></i> Jarak Kabel</span>
                            <div class="ftth-calc-inline">
                                <input type="number" id="fcDistance" min="0" step="0.1" value="1" autocomplete="off">
                                <select class="ftth-calc-unit" id="fcUnit">
                                    <option value="km" selected>KM</option>
                                    <option value="m">M</option>
                                </select>
                            </div>
                        </div>
                        <div class="ftth-calc-field">
                            <span class="ftth-calc-label"><i class="fa-solid fa-link"></i> Sambungan (splice)</span>
                            <input type="number" id="fcSplices" min="0" step="1" value="0" autocomplete="off">
                        </div>
                    </div>
                    <div class="ftth-calc-field">
                        <span class="ftth-calc-label"><i class="fa-solid fa-plug"></i> Jumlah Konektor</span>
                        <select id="fcConnectors">
                            <option value="0" selected>Tanpa konektor</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                        </select>
                    </div>
                    <div class="ftth-calc-result">
                        <div class="ftth-calc-result-title"><i class="fa-solid fa-gauge-high"></i> Hasil Estimasi Redaman</div>
                        <div class="ftth-calc-out" id="fcDropOut"><span><i class="fa-solid fa-arrow-down"></i> Output Power (drop)</span><b id="fcDropPower"></b></div>
                        <div class="ftth-calc-out" id="fcPassOut"><span><i class="fa-solid fa-arrow-right"></i> Next ODP (pass)</span><b id="fcPassPower"></b></div>
                        <div class="ftth-calc-note">Hasil ini dikalkulasikan dengan referensi standar redaman pasif (termasuk rata-rata insertion loss/sambungan dan konektor -0,3-0,5 dB).</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthVisBackdrop" hidden>
        <div class="ftth-modal-card ftth-vis-card" id="ftthVisCard">
            <div class="ftth-modal-head">
                <i class="fa-solid fa-eye"></i>
                Visibility
                <button type="button" class="ftth-modal-close" onclick="ftthCloseVis()" title="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-vis-section"><i class="fa-solid fa-map-location-dot"></i> Fitur Peta</div>
                <div class="ftth-vis-grid ftth-vis-grid-col-5">
                    <label class="ftth-vis-check"><input type="checkbox" id="visRouter" checked><span>Router <small>(induk)</small></span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visOdc"><span>ODC <small>(distribusi)</small></span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visOdp" checked><span>ODP <small>(Point)</small></span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visOtb" checked><span>OTB <small>(Pusat)</small></span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visClosure" checked><span>Closure/JB</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visOnuOnline"><span>ONU Online</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visOnuOffline"><span>ONU Offline</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visPppoeOnline" checked><span>PPPoE Online</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visPppoeOffline" checked><span>PPPoE Offline</span></label>
                </div>
                <div class="ftth-vis-section"><i class="fa-solid fa-tag"></i> Tampilkan Teks ONU</div>
                <select id="visOnuText" class="ftth-vis-select">
                    <option value="nama" selected>Tampilkan Nama</option>
                    <option value="pppoe">Tampilkan PPPoE</option>
                    <option value="sembunyi">Sembunyikan Teks</option>
                </select>
                <div class="ftth-vis-inline">
                    <label class="ftth-vis-check"><input type="checkbox" id="visCable" checked><span><i class="fa-solid fa-diagram-project"></i> Jalur Kabel</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visNotif" checked><span><i class="fa-solid fa-bell"></i> Notifikasi</span></label>
                </div>
                <div class="ftth-vis-section"><i class="fa-solid fa-eye-slash"></i> Sembunyikan Tombol</div>
                <div class="ftth-vis-grid ftth-vis-grid-col">
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideMikrotik"><span>Sync Mikrotik</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideOlt"><span>Sync OLT</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideGenieacs"><span>Sync GenieACS</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideBackup"><span>Backup/restore</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHidePerangkat"><span>Perangkat</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideOnu"><span>Tabel ONU</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideQueue"><span>Queue</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideLock"><span>Kunci Peta</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideMeasure"><span>Ukur Jarak</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideFs"><span>Full screen</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideCalc"><span>Kalkulator Redaman</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideUsers"><span>Users</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideAnim"><span>Animasi</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideNotif"><span>Notifikasi</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideFab"><span>Tombol +</span></label>
                </div>
                <div class="ftth-vis-note"><i class="fa-solid fa-circle-info"></i> Zoom Out akan mengecilkan icon dan menyembunyikan detail.</div>
            </div>
        </div>
    </div>

    <div class="ftth-fab-group">
        <button type="button" class="ftth-fab" title="Menu Utama" onclick="ftthOpenAddDevice()">
            <i class="fa-solid fa-plus"></i>
        </button>
        <div class="ftth-fab-trigger-wrap">
            <button type="button" class="ftth-fab-trigger" id="ftthFabTrigger" onclick="ftthToggleStyles()" title="Gaya Peta">
                <i class="fa-solid fa-layer-group" id="ftthFabTriggerIcon"></i>
            </button>
            <div class="ftth-map-styles" id="ftthMapStyles">
                <button type="button" class="ftth-style-btn" data-layer="peta" onclick="ftthSetLayer('peta')" title="Peta"><i class="fa-solid fa-map"></i><small>Peta</small></button>
                <button type="button" class="ftth-style-btn active" data-layer="satelit" onclick="ftthSetLayer('satelit')" title="Satelit"><i class="fa-solid fa-satellite"></i><small>Satelit</small></button>
                <button type="button" class="ftth-style-btn" data-layer="dark" onclick="ftthSetLayer('dark')" title="Dark"><i class="fa-solid fa-moon"></i><small>Dark</small></button>
                <button type="button" class="ftth-style-btn" data-layer="light" onclick="ftthSetLayer('light')" title="Light"><i class="fa-solid fa-sun"></i><small>Light</small></button>
            </div>
        </div>
    </div>

    <div class="ftth-status">
        <span class="ftth-status-item"><span class="ftth-status-dot online"></span> PPPoE Online: <b id="ftthPppoeOnline">{{ $pppoeOnline }}</b></span>
        <span class="ftth-status-item"><span class="ftth-status-dot offline"></span> PPPoE Offline: <b id="ftthPppoeOffline">{{ $pppoeOffline }}</b></span>
        <span class="ftth-status-item"><span class="ftth-status-dot online"></span> ONU Online: <b id="ftthOnuOnline">{{ $onuOnline }}</b></span>
        <span class="ftth-status-item"><span class="ftth-status-dot offline"></span> ONU Offline: <b id="ftthOnuOffline">{{ $onuOffline }}</b></span>
    </div>

    <div class="ftth-copyright"><i class="fa-regular fa-copyright"></i> {{ now()->year }} PT. Alkonek Network Access. All rights reserved.</div>

    <div class="ftth-modal-backdrop" id="ftthMikrotikBackdrop" hidden>
        <div class="ftth-modal-card" id="ftthMikrotikCard">
            <div class="ftth-modal-head">
                <i class="fa-solid fa-server"></i>
                Sync Mikrotik
                <span class="ftth-mt-status" id="ftthMtStatus"></span>
                <button type="button" class="ftth-modal-close" onclick="ftthCloseMikrotik()" title="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-form">
                    <label for="mtIp">IP Lokal Mikrotik</label>
                    <input type="text" id="mtIp" placeholder="172.10.0.1" autocomplete="off">
                    <label for="mtPort">Port API</label>
                    <input type="number" id="mtPort" placeholder="80" min="1" max="65535">
                    <label for="mtUser">Username</label>
                    <input type="text" id="mtUser" placeholder="admin" autocomplete="off">
                    <label for="mtPass">Password</label>
                    <input type="password" id="mtPass" autocomplete="off">
                </div>
                <div class="ftth-form-actions">
                    <button type="button" class="ftth-modal-btn save" onclick="ftthSaveMikrotik()"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
                    <button type="button" class="ftth-modal-btn" onclick="ftthConnectMikrotik()"><i class="fa-solid fa-plug"></i> Konek</button>
                    <button type="button" class="ftth-modal-btn syncall" onclick="ftthSyncAllMikrotik()"><i class="fa-solid fa-rotate"></i> Sync All Saved Routes</button>
                </div>
                <div class="ftth-router-list" id="ftthRouterList"></div>
            </div>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthOltBackdrop" hidden>
        <div class="ftth-modal-card" id="ftthOltCard">
            <div class="ftth-modal-head">
                <i class="fa-solid fa-tower-broadcast"></i>
                Sync OLT
                <span class="ftth-mt-status" id="ftthOltStatus"></span>
                <button type="button" class="ftth-modal-close" onclick="ftthCloseOlt()" title="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-form">
                    <label for="oltIp">IP OLT</label>
                    <input type="text" id="oltIp" placeholder="172.10.10.2" autocomplete="off">
                    <label for="oltPort">Port SSH</label>
                    <input type="number" id="oltPort" placeholder="22" min="1" max="65535">
                    <label for="oltUser">Username</label>
                    <input type="text" id="oltUser" placeholder="root" autocomplete="off">
                    <label for="oltPass">Password</label>
                    <input type="password" id="oltPass" autocomplete="off">
                    <label for="oltBrand">Brand OLT</label>
                    <select id="oltBrand">
                        <option value="cdata" selected>C-Data</option>
                        <option value="huawei">Huawei</option>
                        <option value="zte">ZTE</option>
                        <option value="fiberhome">FiberHome</option>
                        <option value="vsol">VSOL</option>
                        <option value="hioso">Hioso</option>
                        <option value="hsgq">HSGQ</option>
                        <option value="global">Global</option>
                    </select>
                </div>
                <div class="ftth-form-actions">
                    <button type="button" class="ftth-modal-btn save" onclick="ftthSaveOlt()"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
                    <button type="button" class="ftth-modal-btn" onclick="ftthConnectOlt()"><i class="fa-solid fa-plug"></i> Konek</button>
                    <button type="button" class="ftth-modal-btn syncall" onclick="ftthSyncAllOlt()"><i class="fa-solid fa-rotate"></i> Sync All Saved OLT</button>
                </div>
                <div class="ftth-router-list" id="ftthOltList"></div>
            </div>
        </div>
    </div>

        <div class="ftth-modal-backdrop" id="ftthGenieacsBackdrop" hidden>
        <div class="ftth-modal-card" id="ftthGenieacsCard">
            <div class="ftth-modal-head">
                <i class="fa-solid fa-satellite-dish"></i>
                GenieACS Sync &amp; Config
                <span class="ftth-mt-status" id="ftthGenieacsStatus"></span>
                <button type="button" class="ftth-modal-close" onclick="ftthCloseGenieacs()" title="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-form">
                    <label for="genieacsUrl">URL GenieAcs NBI</label>
                    <input type="text" id="genieacsUrl" placeholder="http://192.168.1.10:7557" autocomplete="off">
                </div>
                <div class="ftth-form-actions">
                    <button type="button" class="ftth-modal-btn save" onclick="ftthSaveGenieacsConfig()"><i class="fa-solid fa-floppy-disk"></i> Simpan Config</button>
                    <button type="button" class="ftth-modal-btn syncall" onclick="ftthSyncGenieacsDevices()"><i class="fa-solid fa-rotate"></i> Syncing with ACS</button>
                </div>
                <div class="ftth-router-list" id="ftthGenieacsSummary"></div>
            </div>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthNotifWaBackdrop" hidden>
        <div class="ftth-modal-card" id="ftthNotifWaCard">
            <div class="ftth-modal-head">
                <i class="fa-brands fa-whatsapp"></i>
                Pengaturan WhatsApp
                <span class="ftth-mt-status" id="ftthNotifWaStatus"></span>
                <button type="button" class="ftth-modal-close" onclick="ftthCloseNotifWa()" title="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">
                <label class="ftth-vis-check"><input type="checkbox" id="notifWaEnabled"><span><strong>Aktifkan WhatsApp</strong></span></label>
                <div class="ftth-form">
                    <label for="notifWaUrl">URL API WhatsApp</label>
                    <input type="text" id="notifWaUrl" placeholder="https://api.whatsapp.com/send" autocomplete="off">
                    <label for="notifWaKey">API Key</label>
                    <input type="password" id="notifWaKey" placeholder="Masukkan API key" autocomplete="off">
                    <label for="notifWaSender">Nomor Pengirim</label>
                    <input type="text" id="notifWaSender" placeholder="628xxxxxxxxxx" autocomplete="off">
                    <label for="notifWaRecipient">Nomor Tujuan</label>
                    <input type="text" id="notifWaRecipient" placeholder="628xxxxxxxxxx" autocomplete="off">
                </div>
                <div class="ftth-form-actions">
                    <button type="button" class="ftth-modal-btn" onclick="ftthCloseNotifWa()"><i class="fa-solid fa-xmark"></i> Batal</button>
                    <button type="button" class="ftth-modal-btn save" onclick="ftthSaveNotifWa()"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
                </div>
            </div>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthNotifTgBackdrop" hidden>
        <div class="ftth-modal-card" id="ftthNotifTgCard">
            <div class="ftth-modal-head">
                <i class="fa-brands fa-telegram"></i>
                Pengaturan Telegram
                <span class="ftth-mt-status" id="ftthNotifTgStatus"></span>
                <button type="button" class="ftth-modal-close" onclick="ftthCloseNotifTg()" title="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">
                <label class="ftth-vis-check"><input type="checkbox" id="notifTgEnabled"><span><strong>Aktifkan Telegram</strong></span></label>
                <div class="ftth-form">
                    <label for="notifTgToken">Bot Token</label>
                    <input type="password" id="notifTgToken" placeholder="123456:ABC-DEF..." autocomplete="off">
                    <label for="notifTgChatId">Chat ID Tujuan</label>
                    <input type="text" id="notifTgChatId" placeholder="-100xxxxxxxxxx" autocomplete="off">
                </div>
                <div class="ftth-form-actions">
                    <button type="button" class="ftth-modal-btn" onclick="ftthCloseNotifTg()"><i class="fa-solid fa-xmark"></i> Batal</button>
                    <button type="button" class="ftth-modal-btn save" onclick="ftthSaveNotifTg()"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
                </div>
            </div>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthQueueBackdrop" hidden>
        <div class="ftth-modal-card" id="ftthQueueCard">
            <div class="ftth-modal-head">
                <i class="fa-solid fa-chart-simple"></i>
                Queue — PPPoE Client
                <span class="ftth-mt-status" id="ftthQueueStatus"></span>
                <button type="button" class="ftth-modal-close" onclick="ftthCloseQueue()" title="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-queue-toolbar">
                    <input type="text" id="ftthQueueSearch" placeholder="Cari user / IP / router / profile..." autocomplete="off">
                    <button type="button" class="ftth-modal-btn" onclick="ftthRefreshQueue()"><i class="fa-solid fa-rotate"></i> Refresh</button>
                </div>
                <div class="ftth-queue-table-wrap" id="ftthQueueWrap">
                    <div class="ftth-router-empty"><i class="fa-solid fa-spinner ftth-spin"></i> Memuat PPPoE client...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthBackupBackdrop" hidden>
        <div class="ftth-modal-card" id="ftthBackupCard">
            <div class="ftth-modal-head">
                <i class="fa-solid fa-database"></i>
                Backup &amp; Restore
                <span class="ftth-mt-status" id="ftthBackupStatus"></span>
                <button type="button" class="ftth-modal-close" onclick="ftthCloseBackup()" title="Tutup">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">

                <div class="ftth-bs ftth-bs-1">
                    <div class="ftth-bs-head"><i class="fa-solid fa-envelope"></i> Auto Backup ke Gmail <span class="ftth-bs-tag">Harian</span></div>
                    <div class="ftth-form ftth-bs-form">
                        <div>
                            <label for="backupEmail">Email penerima Backup</label>
                            <input type="email" id="backupEmail" placeholder="admin@alkonek.net" autocomplete="off">
                        </div>
                        <div>
                            <label for="backupTime">Jam Backup</label>
                            <input type="text" id="backupTime" placeholder="00:00" autocomplete="off">
                        </div>
                    </div>
                    <div class="ftth-bs-actions">
                        <button type="button" class="ftth-backup-btn ftth-bu-1" onclick="ftthSaveBackup()"><i class="fa-solid fa-floppy-disk"></i> Simpan Backup</button>
                        <button type="button" class="ftth-backup-btn ftth-bu-2" onclick="ftthSendBackupNow()"><i class="fa-solid fa-paper-plane"></i> Kirim Sekarang</button>
                    </div>
                </div>

                <div class="ftth-bs ftth-bs-2">
                    <div class="ftth-bs-head"><i class="fa-solid fa-file-arrow-up"></i> Restore File JSON</div>
                    <div class="ftth-bs-actions">
                        <button type="button" class="ftth-backup-btn ftth-bu-3" onclick="ftthRestoreFile('database')"><i class="fa-solid fa-database"></i> Restore database.json</button>
                        <button type="button" class="ftth-backup-btn ftth-bu-4" onclick="ftthRestoreFile('routers')"><i class="fa-solid fa-server"></i> Restore Routers.json</button>
                    </div>
                </div>

                <div class="ftth-bs ftth-bs-3">
                    <div class="ftth-bs-head"><i class="fa-solid fa-file-excel"></i> Backup &amp; Restore Data Excel</div>
                    <div class="ftth-bs-actions">
                        <button type="button" class="ftth-backup-btn ftth-bu-5" onclick="ftthImportExcel()"><i class="fa-solid fa-file-import"></i> Import Data Excel</button>
                        <button type="button" class="ftth-backup-btn ftth-bu-6" onclick="ftthExportExcel()"><i class="fa-solid fa-file-export"></i> Export Data Excel</button>
                    </div>
                </div>

                <div class="ftth-bs ftth-bs-4">
                    <div class="ftth-bs-head"><i class="fa-solid fa-earth-asia"></i> Sinkronisasi Google Earth (KMZ)</div>
                    <div class="ftth-bs-actions">
                        <button type="button" class="ftth-backup-btn ftth-bu-7" onclick="ftthImportKmz()"><i class="fa-solid fa-file-import"></i> Import KML/KMZ</button>
                        <button type="button" class="ftth-backup-btn ftth-bu-8" onclick="ftthExportKmz()"><i class="fa-solid fa-earth-americas"></i> Export Data KMZ</button>
                    </div>
                </div>

                <input type="file" id="ftthRestoreFile" accept=".json,.txt" class="ftth-file-hidden" hidden>
                <input type="file" id="ftthExcelFile" accept=".csv,.txt" class="ftth-file-hidden" hidden>
                <input type="file" id="ftthKmzFile" accept=".kml,.kmz" class="ftth-file-hidden" hidden>

            </div>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthAddDeviceBackdrop" hidden>
        <div class="ftth-modal-card ftth-device-card" id="ftthAddDeviceCard">
            <div class="ftth-modal-head">
                <span class="ftth-modal-title"> <span id="ftthAddDeviceTitle">Tambah Perangkat</span></span>
                <span class="ftth-device-status" id="ftthDeviceStatus"></span>
                <button type="button" class="ftth-modal-close" onclick="ftthCloseAddDevice()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-df">
                    <label>Type Perangkat</label>
                    <select id="ftthDeviceType" onchange="ftthRenderDeviceFields()">
                        <option value="">— Pilih Type Perangkat —</option>
                        <option value="onu">ONU</option>
                        <option value="odp">ODP</option>
                        <option value="htb">HTB</option>
                        <option value="closure">Closure</option>
                        <option value="odc">ODC</option>
                        <option value="otb">OTB</option>
                        <option value="olt">OLT</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
                <div class="ftth-df">
                    <label>Induk</label>
                    <select id="ftthDevParent">
                        <option value="">— Pilih Induk —</option>
                    </select>
                </div>
                <div class="ftth-df">
                    <label>Nama Perangkat</label>
                    <input type="text" id="ftthDevName" placeholder="e.g. ODP Gang 5 / OLT MA5800" autocomplete="off">
                </div>
                <div class="ftth-df">
                    <label>Keterangan</label>
                    <input type="text" id="ftthDevNotes" placeholder="Keterangan opsional..." autocomplete="off">
                </div>
                <div id="ftthDevExtra"></div>
                <div class="ftth-core-chk" id="ftthCoreChkWrap" hidden>
                    <label class="ftth-core-chk-label">
                        <input type="checkbox" id="ftthDevCoreMgmt" onchange="ftthRenderDeviceFields()">
                        <span class="ftth-core-chk-box"><i class="fa-solid fa-check"></i></span>
                        Aktifkan Management Core
                    </label>
                </div>
                <div id="ftthCoreFields" hidden></div>
                <input type="hidden" id="ftthDevLat">
                <input type="hidden" id="ftthDevLng">
                <input type="hidden" id="ftthDevLocation">
                <div class="ftth-form-actions">
                    <button type="button" class="ftth-modal-btn ftth-btn-batal" onclick="ftthCloseAddDevice()"><i class="fa-solid fa-xmark"></i> Batal</button>
                    <button type="button" class="ftth-modal-btn save" onclick="ftthSaveDevice()"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
                </div>
            </div>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthDevicesBackdrop" hidden>
        <div class="ftth-modal-card ftth-device-card ftth-devices-list-card" id="ftthDevicesCard">
            <div class="ftth-modal-head">
                <span class="ftth-modal-title"><i class="fa-solid fa-hdd"></i> Perangkat</span>
                <span class="ftth-device-status" id="ftthDevicesCount"></span>
                <button type="button" class="ftth-modal-close" onclick="ftthCloseDevices()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-device-list" id="ftthDevicesList">
                    <div class="ftth-device-empty">Memuat data perangkat...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthOnuTableBackdrop" hidden>
        <div class="ftth-modal-card ftth-device-card ftth-onu-table-card" id="ftthOnuTableCard">
            <div class="ftth-modal-head">
                <span class="ftth-modal-title"><i class="fa-solid fa-table-list"></i> Tabel ONU</span>
                <span class="ftth-device-status" id="ftthOnuTableCount"></span>
                <button type="button" class="ftth-modal-close" onclick="ftthCloseOnuTable()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-onu-table-wrap">
                    <table class="ftth-onu-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama / Serial</th>
                                <th>MAC</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>Lokasi</th>
                            </tr>
                        </thead>
                        <tbody id="ftthOnuTableBody">
                            <tr><td colspan="6" class="ftth-device-empty">Memuat data ONU...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="ftth-detail-card" id="ftthDetailCard" hidden>
        <div class="ftth-detail-head">
            <span class="ftth-device-type-badge" id="ftthDetailBadge" style="background:#94a3b8">DEVICE</span>
            <span class="ftth-detail-name" id="ftthDetailName">-</span>
            <button type="button" class="ftth-modal-close" onclick="ftthCloseDetail()" title="Tutup">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="ftth-detail-body">
            <span class="ftth-device-row-status" id="ftthDetailStatus">-</span>
            <div class="ftth-detail-row" id="ftthDetailLoc"><i class="fa-solid fa-location-dot"></i><span>-</span></div>
            <div class="ftth-detail-row" id="ftthDetailCoords"><i class="fa-solid fa-map-pin"></i><span>-</span></div>
            <div class="ftth-detail-attrs" id="ftthDetailAttrs"></div>
            <div class="ftth-detail-notes" id="ftthDetailNotes" hidden></div>
            <div class="ftth-detail-live" id="ftthDetailLive" hidden></div>
            <div class="ftth-detail-actions" id="ftthDetailActions"></div>
            <div class="ftth-detail-log" id="ftthDetailLog" hidden></div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    (function() {
        var map = L.map('ftth-map', {
            center: [-6.4860736300793045, 106.01553892262784],
            zoom: 17,
            minZoom: 3,
            maxZoom: 21,
            zoomControl: false,
            attributionControl: false,
            dragging: true,
            scrollWheelZoom: true,
            doubleClickZoom: true,
            boxZoom: true,
            keyboard: false,
            tap: false,
            zoomSnap: 0.25,
            wheelPxPerZoomLevel: 120
        });

        var layerDefs = {
            peta: {
                url: 'https://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}',
                opts: { subdomains: ['mt0', 'mt1', 'mt2', 'mt3'], maxZoom: 21, maxNativeZoom: 21 }
            },
            satelit: {
                url: 'https://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',
                opts: { subdomains: ['mt0', 'mt1', 'mt2', 'mt3'], maxZoom: 21, maxNativeZoom: 21 }
            },
            dark: {
                url: 'https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png',
                opts: { subdomains: ['a', 'b', 'c', 'd'], maxZoom: 21, maxNativeZoom: 20 }
            },
            light: {
                url: 'https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png',
                opts: { subdomains: ['a', 'b', 'c', 'd'], maxZoom: 21, maxNativeZoom: 20 }
            }
        };

        var layers = {};
        var current = 'satelit';
        Object.keys(layerDefs).forEach(function(name) {
            layers[name] = L.tileLayer(layerDefs[name].url, layerDefs[name].opts);
        });
        layers[current].addTo(map);
        document.getElementById('ftth-map').classList.add('ftth-layer-' + current);

        window.ftthSetLayer = function(name) {
            if (!layers[name] || name === current) return;
            map.removeLayer(layers[current]);
            layers[name].addTo(map);
            current = name;
            var mapEl = document.getElementById('ftth-map');
            mapEl.classList.remove('ftth-layer-peta', 'ftth-layer-satelit', 'ftth-layer-dark', 'ftth-layer-light');
            mapEl.classList.add('ftth-layer-' + name);
            document.querySelectorAll('.ftth-style-btn').forEach(function(btn) {
                btn.classList.toggle('active', btn.dataset.layer === name);
            });
        };

        window.ftthToggleStyles = function() {
            var panel = document.getElementById('ftthMapStyles');
            var trigger = document.getElementById('ftthFabTrigger');
            var icon = document.getElementById('ftthFabTriggerIcon');
            var open = panel.classList.toggle('open');
            trigger.classList.toggle('active', open);
            icon.classList.toggle('fa-xmark', open);
            icon.classList.toggle('fa-layer-group', !open);
        };

        /* ── Kunci Peta: matikan geser & zoom agar tampilan tetap ── */
        var mapLocked = false;
        var mapInteractions = ['dragging', 'scrollWheelZoom', 'doubleClickZoom', 'boxZoom', 'touchZoom'];

        function setMapLock(locked) {
            mapLocked = locked;
            mapInteractions.forEach(function(name) {
                var h = map[name];
                if (!h) return;
                if (locked) { if (h.disable) h.disable(); }
                else { if (h.enable) h.enable(); }
            });
            map.getContainer().classList.toggle('ftth-map-locked', locked);
            var btn = document.getElementById('ftthLockBtn');
            var icon = document.getElementById('ftthLockIcon');
            if (btn) {
                btn.classList.toggle('active', locked);
                btn.setAttribute('title', locked ? 'Buka Kunci Peta' : 'Kunci Peta');
            }
            if (icon) {
                icon.classList.toggle('fa-lock', !locked);
                icon.classList.toggle('fa-unlock', locked);
            }
            ftthToast(locked ? 'Peta terkunci' : 'Peta dibuka kunci', 'info');
        }

        window.ftthToggleLock = function() { setMapLock(!mapLocked); };

        /* ── Toggle animasi: hidupkan/matikan semua animasi peta ── */
        window.ftthToggleAnim = function() {
            var off = document.body.classList.toggle('ftth-anim-off');
            var btn = document.getElementById('ftthAnimBtn');
            var icon = document.getElementById('ftthAnimIcon');
            if (btn) {
                btn.classList.toggle('active', !off);
                btn.setAttribute('title', off ? 'Aktifkan Animasi' : 'Matikan Animasi');
            }
            if (icon) {
                icon.classList.toggle('fa-circle-play', !off);
                icon.classList.toggle('fa-circle-pause', off);
            }
            ftthToast(off ? 'Animasi dimatikan' : 'Animasi diaktifkan', 'info');
        };

        /* ── Full Screen: sembunyikan fitur, zoom-in smooth, kembali saat off ── */
        var fsState = { active: false, center: null, zoom: null };

        window.ftthToggleFullscreen = function() {
            var body = document.body;
            var btn = document.getElementById('ftthFullscreenBtn');
            var icon = document.getElementById('ftthFullscreenIcon');

            if (!fsState.active) {
                fsState.active = true;
                fsState.center = map.getCenter();
                fsState.zoom = map.getZoom();
                body.classList.add('ftth-fs-active');
                map.flyTo(fsState.center, fsState.zoom + 1, { duration: 0.8 });
                if (icon) { icon.classList.remove('fa-expand'); icon.classList.add('fa-compress'); }
                if (btn) btn.setAttribute('title', 'Keluar Full Screen');
            } else {
                body.classList.remove('ftth-fs-active');
                map.flyTo(fsState.center, fsState.zoom, { duration: 0.8 });
                if (icon) { icon.classList.remove('fa-compress'); icon.classList.add('fa-expand'); }
                if (btn) btn.setAttribute('title', 'Full Screen');
                fsState.active = false;
                ftthToast('Keluar dari Full Screen', 'ok');
            }
        };

        /* ── Penggaris Ukur: dropdown mode (Ukur / OTDR) + pengukuran ── */
        var measureMenuOpen = false;

        function measureBtnState() {
            var btn = document.getElementById('ftthMeasureBtn');
            if (btn) btn.classList.toggle('active', measureMenuOpen || measure.active);
        }

        window.ftthToggleMeasureMenu = function() {
            var menu = document.getElementById('ftthMeasureMenu');
            if (!menu) return;
            measureMenuOpen = !measureMenuOpen;
            menu.classList.toggle('open', measureMenuOpen);
            measureBtnState();
        };

        document.addEventListener('click', function(e) {
            if (!measureMenuOpen) return;
            if (e.target && e.target.closest && e.target.closest('.ftth-measure-wrap')) return;
            measureMenuOpen = false;
            var menu = document.getElementById('ftthMeasureMenu');
            if (menu) menu.classList.remove('open');
            measureBtnState();
        });

        var notifMenuOpen = false;

        window.ftthToggleNotifMenu = function() {
            var menu = document.getElementById('ftthNotifMenu');
            if (!menu) return;
            notifMenuOpen = !notifMenuOpen;
            menu.classList.toggle('open', notifMenuOpen);
            notifBtnState();
        };

        function notifBtnState() {
            var btn = document.getElementById('ftthNotifBtn');
            if (btn) btn.classList.toggle('active', notifMenuOpen);
        }

        document.addEventListener('click', function(e) {
            if (!notifMenuOpen) return;
            if (e.target && e.target.closest && e.target.closest('.ftth-notif-wrap')) return;
            notifMenuOpen = false;
            var menu = document.getElementById('ftthNotifMenu');
            if (menu) menu.classList.remove('open');
            notifBtnState();
        });

        var MEASURE = {
            OTDR_ROUTE_FACTOR: 1.2,   // kabel diperkirakan 20% lebih panjang dari jarak garis lurus
            OTDR_DB_PER_KM: 0.35,     // redaman kabel SMF @1310nm (estimasi per km)
            OTDR_SPLICE_DB: 0.3,      // loss sambungan fusi per joint antar ruas
            OTDR_CONNECTOR_DB: 0.5    // loss total konektor (2 ujung)
        };

        var measure = { active: false, finished: false, mode: null, points: [], line: null, ghost: null, markers: [], labels: [] };

        function measureColor() { return measure.mode === 'otdr' ? '#38bdf8' : '#4ade80'; }
        function fmtMeters(m) {
            if (m >= 1000) return (m / 1000).toFixed(2) + ' km';
            return Math.round(m) + ' m';
        }
        function measurePointIcon() {
            return L.divIcon({ className: 'ftth-measure-point' + (measure.mode === 'otdr' ? ' otdr' : ''), iconSize: [12, 12], iconAnchor: [6, 6] });
        }

        function measureClearLayers() {
            if (measure.line) { map.removeLayer(measure.line); measure.line = null; }
            if (measure.ghost) { map.removeLayer(measure.ghost); measure.ghost = null; }
            measure.markers.forEach(function(m) { map.removeLayer(m); });
            measure.labels.forEach(function(l) { map.removeLayer(l); });
            measure.markers = [];
            measure.labels = [];
        }

        function measureRenderResult() {
            var card = document.getElementById('ftthMeasureResult');
            if (!card) return;
            var isOtdr = measure.mode === 'otdr';
            var total = 0;
            var segs = [];
            for (var i = 1; i < measure.points.length; i++) {
                var d = map.distance(measure.points[i - 1], measure.points[i]);
                total += d;
                segs.push(d);
            }

            var body, hint, title;
            if (isOtdr) {
                var fiberLen = total * MEASURE.OTDR_ROUTE_FACTOR;
                var loss = (fiberLen / 1000) * MEASURE.OTDR_DB_PER_KM;
                if (segs.length) loss += (segs.length - 1) * MEASURE.OTDR_SPLICE_DB + MEASURE.OTDR_CONNECTOR_DB;
                title = 'OTDR — Estimasi';
                body = '<div class="fm-row"><span>Jarak garis lurus</span><b>' + fmtMeters(total) + '</b></div>' +
                       '<div class="fm-row"><span>Panjang kabel</span><b>' + fmtMeters(fiberLen) + '</b></div>' +
                       '<div class="fm-row"><span>Redaman total</span><b>' + loss.toFixed(2) + ' dB</b></div>' +
                       '<div class="fm-row"><span>Ruas / Titik</span><b>' + segs.length + ' / ' + measure.points.length + '</b></div>';
                hint = measure.finished
                    ? 'Estimasi: kabel ×' + MEASURE.OTDR_ROUTE_FACTOR + ', redaman ' + MEASURE.OTDR_DB_PER_KM + ' dB/km + sambungan.'
                    : 'Klik titik-titik pada jalur kabel. Selesai / klik kanan untuk mengakhiri.';
            } else {
                title = 'Pengukuran Jarak';
                body = '<div class="fm-row"><span>Total jarak</span><b>' + fmtMeters(total) + '</b></div>' +
                       '<div class="fm-row"><span>Titik</span><b>' + measure.points.length + '</b></div>';
                hint = measure.finished
                    ? 'Pengukuran selesai. Klik Hapus untuk mengulang.'
                    : 'Klik titik di peta untuk menambah ruas. Selesai / klik kanan untuk mengakhiri.';
            }

            card.classList.toggle('ftth-measure-otdr', isOtdr);
            document.getElementById('ftthMeasureTitle').textContent = title;
            document.getElementById('ftthMeasureBody').innerHTML = body;
            document.getElementById('ftthMeasureHint').textContent = hint;
            var sBtn = document.getElementById('ftthMeasureSelesaiBtn');
            if (sBtn) sBtn.classList.toggle('ftth-measure-otdr-act', isOtdr);
            card.style.display = 'block';
            measureBtnState();
        }

        function measureDraw() {
            measureClearLayers();
            var pts = measure.points;
            if (pts.length === 1) {
                measure.markers.push(L.marker(pts[0], { icon: measurePointIcon(), interactive: false }).addTo(map));
            } else if (pts.length > 1) {
                measure.line = L.polyline(pts, { color: measureColor(), weight: 2.5, dashArray: '6 4', opacity: 0.95 }).addTo(map);
                pts.forEach(function(p) {
                    measure.markers.push(L.marker(p, { icon: measurePointIcon(), interactive: false }).addTo(map));
                });
                for (var i = 1; i < pts.length; i++) {
                    var d = map.distance(pts[i - 1], pts[i]);
                    var mid = [(pts[i - 1][0] + pts[i][0]) / 2, (pts[i - 1][1] + pts[i][1]) / 2];
                    var lbl = L.marker(mid, {
                        icon: L.divIcon({ className: 'ftth-measure-label' + (measure.mode === 'otdr' ? ' otdr' : ''), html: fmtMeters(d), iconSize: null }),
                        interactive: false
                    }).addTo(map);
                    measure.labels.push(lbl);
                }
            }
            measureRenderResult();
        }

        window.ftthMeasureStart = function(mode) {
            measureMenuOpen = false;
            var menu = document.getElementById('ftthMeasureMenu');
            if (menu) menu.classList.remove('open');
            measureBtnState();

            measureClearLayers();
            measure.points = [];
            measure.mode = mode;
            measure.active = true;
            measure.finished = false;
            map.doubleClickZoom.disable();
            measureDraw();
            ftthToast(mode === 'otdr' ? 'Mode OTDR aktif — klik titik pada jalur kabel' : 'Mode Ukur aktif — klik titik untuk mengukur jarak', 'info');
        };

        window.ftthMeasureSelesai = function() {
            if (!measure.active || measure.finished) return;
            if (measure.points.length < 2) { ftthToast('Klik minimal 2 titik di peta', 'warn'); return; }
            measure.active = false;
            measure.finished = true;
            if (measure.ghost) { map.removeLayer(measure.ghost); measure.ghost = null; }
            measureRenderResult();
            ftthToast('Pengukuran selesai', 'ok');
        };

        window.ftthMeasureHapus = function() {
            if (!measure.mode) return;
            measure.points = [];
            measure.active = true;
            measure.finished = false;
            measureClearLayers();
            measureRenderResult();
        };

        window.ftthMeasureClose = function() {
            measure.active = false;
            measure.finished = false;
            measure.mode = null;
            measure.points = [];
            measureClearLayers();
            var card = document.getElementById('ftthMeasureResult');
            if (card) card.style.display = 'none';
            if (!mapLocked) map.doubleClickZoom.enable();
            measureBtnState();
        };

        map.on('click', function(e) {
            if (!measure.active || measure.finished) return;
            var p = [e.latlng.lat, e.latlng.lng];
            if (measure.points.length) {
                var last = measure.points[measure.points.length - 1];
                if (map.distance(last, p) < 5) return;
            }
            measure.points.push(p);
            measureDraw();
        });

        map.on('mousemove', function(e) {
            if (!measure.active || measure.finished || measure.points.length === 0) return;
            if (measure.ghost) map.removeLayer(measure.ghost);
            var pts = measure.points.slice();
            pts.push([e.latlng.lat, e.latlng.lng]);
            measure.ghost = L.polyline(pts, { color: measureColor(), weight: 2, dashArray: '3 6', opacity: 0.6 }).addTo(map);
        });

        map.on('dblclick', function() { ftthMeasureSelesai(); });
        map.on('contextmenu', function(e) {
            if (!measure.active || measure.finished) return;
            L.DomEvent.stop(e);
            ftthMeasureSelesai();
        });

        /* ── Kalkulator ODP: hitung redaman dari rasio splitter ── */
        var CALC = {
            plc: { 2: 3.7, 4: 7.3, 8: 10.5, 16: 13.9, 32: 17.1, 64: 20.5 },
            dbPerKm: 0.35,
            spliceDb: 0.3,
            connectorDb: 0.5,
            onuSensitivity: -27
        };

        window.ftthOpenCalc = function() {
            document.getElementById('ftthCalcBackdrop').hidden = false;
            positionCardBelow(document.getElementById('ftthCalcCard'), document.querySelector('.ftth-ac-calculator'));
            ftthCalcUpdate();
        };

        window.ftthCloseCalc = function() {
            document.getElementById('ftthCalcBackdrop').hidden = true;
        };

        window.ftthCalcFlipSign = function() {
            var el = document.getElementById('fcInputPower');
            var v = parseFloat(el.value);
            if (isNaN(v) || v === 0) {
                el.value = 3;
            } else {
                el.value = -v;
            }
            ftthCalcUpdate();
        };

        function ftthCalcNum(id) {
            var v = parseFloat(document.getElementById(id).value);
            return isNaN(v) || v < 0 ? 0 : v;
        }

        function ftthCalcUpdate() {
            var pin = parseFloat(document.getElementById('fcInputPower').value);
            if (isNaN(pin)) pin = 0;

            var ratio = document.getElementById('fcRatio').value;
            var x = 0;
            if (ratio) x = parseInt(ratio.split(':')[0], 10) || 0;

            var plc = parseInt(document.getElementById('fcPlc').value, 10) || 0;
            var unit = document.getElementById('fcUnit').value;
            var dist = ftthCalcNum('fcDistance');
            var km = unit === 'm' ? dist / 1000 : dist;
            var splices = Math.round(ftthCalcNum('fcSplices'));
            var connectors = parseInt(document.getElementById('fcConnectors').value, 10) || 0;

            var upstream = km * CALC.dbPerKm + splices * CALC.spliceDb + connectors * CALC.connectorDb;
            var base = pin - upstream;

            var tapLoss = ratio ? 10 * Math.log10(100 / x) : 0;
            var throughLoss = ratio ? 10 * Math.log10(100 / (100 - x)) : 0;
            var plcLoss = plc ? CALC.plc[plc] : 0;

            var drop = base - tapLoss - plcLoss;
            var pass = base - throughLoss;

            document.getElementById('fcDropPower').textContent = drop.toFixed(2) + ' dBm';
            document.getElementById('fcPassPower').textContent = pass.toFixed(2) + ' dBm';
            document.getElementById('fcDropOut').classList.toggle('bad', drop < CALC.onuSensitivity);
            document.getElementById('fcPassOut').classList.toggle('bad', pass < CALC.onuSensitivity);
        }

        document.getElementById('fcRatio').addEventListener('change', ftthCalcUpdate);
        document.getElementById('fcPlc').addEventListener('change', ftthCalcUpdate);
        document.getElementById('fcUnit').addEventListener('change', ftthCalcUpdate);
        document.getElementById('fcConnectors').addEventListener('change', ftthCalcUpdate);
        ['fcInputPower', 'fcDistance', 'fcSplices'].forEach(function(id) {
            document.getElementById(id).addEventListener('input', ftthCalcUpdate);
        });

        /* ── Visibility: filter layer & sembunyikan tombol ── */
        var VIS = {
            router: true,
            odc: false,
            odp: true,
            otb: true,
            closure: true,
            onuOnline: false,
            onuOffline: false,
            onuText: 'nama',
            cable: true,
            notif: true,
            hideMikrotik: false,
            hideOlt: false,
            hideGenieacs: false,
            hideBackup: false,
            hidePerangkat: false,
            hideOnu: false,
            hideMeasure: false,
            hideCalc: false,
            hideFs: false,
            hideQueue: false,
            hideAnim: false,
            hideUsers: false,
            hideNotif: false,
            hideLock: false,
            hideFab: false
        };

        var VIS_BTN = [
            ['hideMikrotik', '.ftth-ac-mikrotik'],
            ['hideOlt', '.ftth-ac-olt'],
            ['hideGenieacs', '.ftth-ac-genieacs'],
            ['hideBackup', '.ftth-ac-backup'],
            ['hidePerangkat', '.ftth-ac-perangkat'],
            ['hideOnu', '.ftth-ac-onu'],
            ['hideMeasure', '.ftth-measure-wrap'],
            ['hideCalc', '.ftth-ac-calculator'],
            ['hideFs', '.ftth-fs-btn'],
            ['hideQueue', '.ftth-ac-queue'],
            ['hideAnim', '.ftth-ac-anim'],
            ['hideUsers', '.ftth-ac-users'],
            ['hideLock', '.ftth-ac-lock'],
            ['hideFab', '.ftth-fab-group']
        ];

        function ftthVisCollect() {
            function val(id) { return document.getElementById(id).checked; }
            return {
                router: val('visRouter'),
                odc: val('visOdc'),
                odp: val('visOdp'),
                otb: val('visOtb'),
                closure: val('visClosure'),
                onuOnline: val('visOnuOnline'),
                onuOffline: val('visOnuOffline'),
                onuText: document.getElementById('visOnuText').value,
                cable: val('visCable'),
                notif: val('visNotif'),
                hideMikrotik: val('visHideMikrotik'),
                hideOlt: val('visHideOlt'),
                hideGenieacs: val('visHideGenieacs'),
                hideBackup: val('visHideBackup'),
                hidePerangkat: val('visHidePerangkat'),
                hideOnu: val('visHideOnu'),
                hideMeasure: val('visHideMeasure'),
                hideCalc: val('visHideCalc'),
                hideFs: val('visHideFs'),
                hideQueue: val('visHideQueue'),
                hideAnim: val('visHideAnim'),
                hideUsers: val('visHideUsers'),
                hideNotif: val('visHideNotif'),
                hideLock: val('visHideLock'),
                hideFab: val('visHideFab')
            };
        }

        function ftthVisApply() {
            VIS = ftthVisCollect();
            VIS_BTN.forEach(function(pair) {
                var el = document.querySelector(pair[1]);
                if (el) el.classList.toggle('ftth-vis-hidden', VIS[pair[0]]);
            });
            var notifBtn = document.querySelector('.ftth-ac-notifications');
            var notifWrap = document.querySelector('.ftth-notif-wrap');
            var notifHidden = !VIS.notif || VIS.hideNotif;
            if (notifBtn) notifBtn.classList.toggle('ftth-vis-hidden', notifHidden);
            if (notifWrap) notifWrap.classList.toggle('ftth-vis-hidden', notifHidden);
            if (markersCache) renderMapMarkers();
        }

        window.ftthOpenVis = function() {
            document.getElementById('ftthVisBackdrop').hidden = false;
            positionCardBelow(document.getElementById('ftthVisCard'), document.querySelector('.ftth-ac-visibility'));
        };

        window.ftthCloseVis = function() {
            document.getElementById('ftthVisBackdrop').hidden = true;
        };

        ['visRouter', 'visOdc', 'visOdp', 'visOtb', 'visClosure', 'visOnuOnline', 'visOnuOffline',
            'visOnuText', 'visCable', 'visNotif',
            'visHideMikrotik', 'visHideOlt', 'visHideGenieacs', 'visHideBackup', 'visHidePerangkat',
            'visHideOnu', 'visHideMeasure', 'visHideCalc', 'visHideFs',
            'visHideQueue', 'visHideAnim', 'visHideUsers', 'visHideNotif', 'visHideLock', 'visHideFab'
        ].forEach(function(id) {
            document.getElementById(id).addEventListener('change', ftthVisApply);
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (fsState.active) { ftthToggleFullscreen(); return; }
                if (!document.getElementById('ftthCalcBackdrop').hidden) { ftthCloseCalc(); return; }
                if (!document.getElementById('ftthVisBackdrop').hidden) { ftthCloseVis(); return; }
                if (!document.getElementById('ftthNotifWaBackdrop').hidden) { ftthCloseNotifWa(); return; }
                if (!document.getElementById('ftthNotifTgBackdrop').hidden) { ftthCloseNotifTg(); return; }
                if (measure.active && !measure.finished) ftthMeasureSelesai();
                else if (measure.finished) ftthMeasureClose();
            }
        });

        map.on('click', function(e) {
            if (e.originalEvent && e.originalEvent.target) {
                var t = e.originalEvent.target;
                if (t.closest && t.closest('.leaflet-marker-icon')) return;
            }
            ftthCloseDetail();
        });

        var searchMarker = null;
        var toastTimer = null;

        var searchIcon = L.divIcon({
            className: 'ftth-search-marker',
            html: '<i class="fa-solid fa-location-dot"></i>',
            iconSize: [32, 32],
            iconAnchor: [16, 30],
            popupAnchor: [0, -28]
        });

        window.ftthToast = function(msg, type) {
            var t = document.getElementById('ftthToast');
            if (!t) {
                t = document.createElement('div');
                t.id = 'ftthToast';
                t.className = 'ftth-toast';
                document.body.appendChild(t);
            }
            t.textContent = msg;
            t.dataset.type = type || 'info';
            t.classList.add('show');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(function() { t.classList.remove('show'); }, 3200);
        };

        function parseCoords(q) {
            var m = q.match(/^(-?\d{1,3}(?:\.\d+)?)\s*[,; ]\s*(-?\d{1,3}(?:\.\d+)?)$/);
            if (!m) return null;
            var lat = parseFloat(m[1]);
            var lng = parseFloat(m[2]);
            if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
            return [lat, lng];
        }

        function gotoPoint(lat, lng, label) {
            if (searchMarker) map.removeLayer(searchMarker);
            searchMarker = L.marker([lat, lng], { icon: searchIcon }).addTo(map)
                .bindPopup(label).openPopup();
            map.flyTo([lat, lng], Math.max(map.getZoom(), 16), { duration: 1.2 });
            ftthToast('Lokasi ditemukan', 'ok');
        }

        window.ftthSearch = function() {
            closeSuggest();
            var input = document.getElementById('ftthSearchInput');
            var q = input ? input.value.trim() : '';
            if (!q) {
                ftthToast('Ketik koordinat atau alamat terlebih dahulu', 'warn');
                if (input) input.focus();
                return;
            }

            var coords = parseCoords(q);
            if (coords) {
                gotoPoint(coords[0], coords[1], 'Koordinat: ' + coords[0] + ', ' + coords[1]);
                return;
            }

            ftthToast('Mencari "' + q + '"...', 'info');
            fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data || !data.length) {
                    ftthToast('Lokasi tidak ditemukan', 'error');
                    return;
                }
                var r = data[0];
                gotoPoint(parseFloat(r.lat), parseFloat(r.lon), r.display_name);
            })
            .catch(function() {
                ftthToast('Gagal mencari lokasi. Periksa koneksi.', 'error');
            });
        };

        var inputEl = document.getElementById('ftthSearchInput');
        if (inputEl) {
            inputEl.value = '';
            inputEl.setAttribute('autocomplete', 'off');
            inputEl.setAttribute('readonly', 'readonly');
            inputEl.addEventListener('focus', function() {
                this.removeAttribute('readonly');
            });
        }
        var suggestEl = document.getElementById('ftthSearchSuggest');
        var suggestTimer = null;
        var suggestData = [];
        var suggestActive = -1;

        function escapeHtml(s) {
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function closeSuggest() {
            if (!suggestEl) return;
            suggestEl.classList.remove('show');
            suggestEl.innerHTML = '';
            suggestData = [];
            suggestActive = -1;
        }

        function markActive() {
            suggestEl.querySelectorAll('.ftth-sug-item').forEach(function(el, i) {
                el.classList.toggle('active', i === suggestActive);
            });
        }

        function pickSuggestion(idx) {
            var d = suggestData[idx];
            if (!d) return;
            inputEl.value = d.label;
            closeSuggest();
            if (d.lat == null || d.lon == null) {
                ftthToast('Tidak ada koordinat untuk "'.concat(d.label.slice(0, 35), '"'), 'warn');
                return;
            }
            gotoPoint(d.lat, d.lon, d.label);
        }

        function renderSuggest(list) {
            suggestData = list || [];
            suggestActive = -1;
            if (!suggestData.length) {
                suggestEl.innerHTML = '<div class="ftth-sug-empty">Tidak ada hasil</div>';
            } else {
                suggestEl.innerHTML = suggestData.map(function(d, i) {
                    var badge = d.type ? '<span class="ftth-sug-badge">' + escapeHtml(d.type) + '</span>' : '';
                    return '<div class="ftth-sug-item" data-i="' + i + '"><i class="fa-solid fa-location-dot"></i><span class="ftth-sug-label">' + escapeHtml(d.label) + '</span>' + badge + '</div>';
                }).join('');
                suggestEl.querySelectorAll('.ftth-sug-item').forEach(function(el) {
                    el.addEventListener('click', function() {
                        pickSuggestion(parseInt(el.dataset.i, 10));
                    });
                });
            }
            suggestEl.classList.add('show');
        }

        function loadSuggestions(q) {
            var nominatim = fetch('https://nominatim.openstreetmap.org/search?format=json&limit=5&countrycodes=id&q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' }
            })
            .then(function(res) { return res.json(); })
            .then(function(list) {
                return (list || []).map(function(d) {
                    return { type: null, label: d.display_name, lat: parseFloat(d.lat), lon: parseFloat(d.lon) };
                });
            })
            .catch(function() { return []; });

            var local = fetch('/noc/features/map/search?q=' + encodeURIComponent(q))
                .then(function(res) { return res.json(); })
                .then(function(list) { return list || []; })
                .catch(function() { return []; });

            Promise.all([local, nominatim]).then(function(results) {
                renderSuggest(results[0].concat(results[1]).slice(0, 8));
            });
        }

        if (inputEl) {
            inputEl.addEventListener('input', function() {
                clearTimeout(suggestTimer);
                var q = this.value.trim();
                if (!q || parseCoords(q) || q.length < 2) { closeSuggest(); return; }
                suggestTimer = setTimeout(function() { loadSuggestions(q); }, 350);
            });

            inputEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (suggestActive >= 0 && suggestData[suggestActive]) {
                        pickSuggestion(suggestActive);
                    } else {
                        ftthSearch();
                    }
                } else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (suggestData.length) { suggestActive = (suggestActive + 1) % suggestData.length; markActive(); }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (suggestData.length) { suggestActive = (suggestActive - 1 + suggestData.length) % suggestData.length; markActive(); }
                } else if (e.key === 'Escape') {
                    closeSuggest();
                }
            });

            document.addEventListener('click', function(e) {
                if (!e.target.closest('.ftth-search')) closeSuggest();
            });
        }

        /* ── Sync Mikrotik modal ── */
        function csrfToken() {
            var m = document.querySelector('meta[name="csrf-token"]');
            return m ? m.getAttribute('content') : '';
        }

        function mtApi(path, method, body) {
            return fetch(path, {
                method: method || 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'application/json'
                },
                body: body ? JSON.stringify(body) : undefined
            }).then(function(res) {
                return res.json().catch(function() { return {}; }).then(function(d) {
                    return { status: res.status, data: d };
                });
            });
        }

        var mtBusy = false;
        var oltBusy = false;
        function setCardBusy(cardId, b) {
            var card = document.getElementById(cardId);
            if (!card) return;
            card.querySelectorAll('.ftth-modal-btn').forEach(function(x) { x.disabled = b; });
        }
        function setMtBusy(b) {
            mtBusy = b;
            setCardBusy('ftthMikrotikCard', b);
        }
        function setOltBusy(b) {
            oltBusy = b;
            setCardBusy('ftthOltCard', b);
        }

        function setMtStatus(msg, type, spin) {
            var s = document.getElementById('ftthMtStatus');
            if (!s) return;
            s.className = 'ftth-mt-status ' + (type || 'info');
            s.innerHTML = (spin ? '<i class="fa-solid fa-spinner ftth-spin"></i> ' : '') + escapeHtml(msg);
        }

        function positionCardBelow(card, btn) {
            if (!card) return;
            var pad = 8;
            var cw = card.offsetWidth || 280;
            var ch = card.offsetHeight || 400;
            var left, top;
            if (btn) {
                var r = btn.getBoundingClientRect();
                left = r.left;
                top = r.bottom + pad;
                if (left + cw > window.innerWidth - pad) left = Math.max(pad, window.innerWidth - cw - pad);
                if (top + ch > window.innerHeight - pad) top = Math.max(pad, window.innerHeight - ch - pad);
            } else {
                left = window.innerWidth / 2 - cw / 2;
                top = window.innerHeight / 2 - ch / 2;
            }
            card.style.left = left + 'px';
            card.style.top = top + 'px';
            card.style.transform = 'none';
        }

        window.ftthOpenMikrotik = function() {
            var bd = document.getElementById('ftthMikrotikBackdrop');
            bd.hidden = false;
            positionCardBelow(document.getElementById('ftthMikrotikCard'), document.querySelector('.ftth-ac-mikrotik'));
            loadRouterList();
        };

        window.ftthCloseMikrotik = function() {
            document.getElementById('ftthMikrotikBackdrop').hidden = true;
        };

        document.querySelectorAll('.ftth-modal-card').forEach(function(card) {
            var head = card.querySelector('.ftth-modal-head');
            var dg = false, ox = 0, oy = 0, L = 0, T = 0;
            head.addEventListener('mousedown', function(e) {
                if (e.target.closest('.ftth-modal-close')) return;
                dg = true;
                ox = e.clientX;
                oy = e.clientY;
                var r = card.getBoundingClientRect();
                L = r.left;
                T = r.top;
                card.style.left = L + 'px';
                card.style.top = T + 'px';
                card.style.transform = 'none';
                head.style.cursor = 'grabbing';
                e.preventDefault();
            });
            document.addEventListener('mousemove', function(e) {
                if (!dg) return;
                card.style.left = (L + e.clientX - ox) + 'px';
                card.style.top = (T + e.clientY - oy) + 'px';
            });
            document.addEventListener('mouseup', function() {
                dg = false;
                head.style.cursor = 'grab';
            });
        })

        function mtReadForm() {
            var ip = document.getElementById('mtIp').value.trim();
            var port = document.getElementById('mtPort').value.trim();
            var user = document.getElementById('mtUser').value.trim();
            var pass = document.getElementById('mtPass').value;
            if (!ip) { ftthToast('Isi IP lokal Mikrotik', 'warn'); return null; }
            if (!port || isNaN(parseInt(port, 10))) { ftthToast('Isi port API', 'warn'); return null; }
            if (!user) { ftthToast('Isi username', 'warn'); return null; }
            if (!pass) { ftthToast('Isi password', 'warn'); return null; }
            return { ip: ip, port: parseInt(port, 10), username: user, password: pass };
        }

        function renderRouterList(routers) {
            var list = document.getElementById('ftthRouterList');
            if (!routers.length) {
                list.innerHTML = '<div class="ftth-router-empty">Belum ada router tersimpan</div>';
                return;
            }
            list.innerHTML = routers.map(function(r) {
                var dotColor = r.status === 'online' ? '#22c55e' : '#ef4444';
                var ver = r.routeros_version ? 'v' + escapeHtml(r.routeros_version) : 'v?';
                return '<div class="ftth-router-row" data-id="' + r.id + '">' +
                    '<span class="ftth-router-info">' +
                        '<span class="ftth-router-line"><span class="dot" style="background:' + dotColor + '"></span>' + escapeHtml(r.ip) + ' : ' + escapeHtml(r.port) + '</span>' +
                        '<span class="ftth-router-version">' + ver + '</span>' +
                    '</span>' +
                    '<span class="ftth-router-actions">' +
                        '<button type="button" class="ftth-row-btn sync" onclick="ftthSyncRouter(' + r.id + ')"><i class="fa-solid fa-rotate"></i> Sync</button>' +
                        '<button type="button" class="ftth-row-btn del" onclick="ftthDelRouter(' + r.id + ')"><i class="fa-solid fa-trash"></i> Del</button>' +
                    '</span>' +
                '</div>';
            }).join('');
        }

        function loadRouterList() {
            var list = document.getElementById('ftthRouterList');
            if (!list) return;
            list.innerHTML = '<div class="ftth-router-empty"><i class="fa-solid fa-spinner ftth-spin"></i> Memuat...</div>';
            mtApi('/noc/features/map/mikrotik', 'GET').then(function(r) {
                renderRouterList(r.data.routers || []);
            }).catch(function() {
                list.innerHTML = '<div class="ftth-router-empty">Gagal memuat daftar router</div>';
            });
        }

        window.ftthSaveMikrotik = function() {
            if (mtBusy) return;
            var payload = mtReadForm();
            if (!payload) return;
            setMtBusy(true);
            setMtStatus('Menyimpan...', 'info', true);
            mtApi('/noc/features/map/mikrotik/save', 'POST', payload).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || 'Gagal menyimpan router', 'error');
                    setMtStatus(r.data.error || 'Gagal simpan', 'fail');
                    return;
                }
                ftthToast('Router tersimpan', 'ok');
                setMtStatus('Tersimpan', 'ok');
                renderRouterList(r.data.routers || []);
            }).catch(function() {
                ftthToast('Gagal menyimpan router', 'error');
                setMtStatus('Gagal simpan', 'fail');
            }).then(function() { setMtBusy(false); });
        };

        window.ftthConnectMikrotik = function() {
            if (mtBusy) return;
            var payload = mtReadForm();
            if (!payload) return;
            setMtBusy(true);
            setMtStatus('Menghubungkan...', 'info', true);
            mtApi('/noc/features/map/mikrotik/save', 'POST', payload).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || 'Simpan router dulu untuk konek', 'error');
                    setMtStatus(r.data.error || 'Konek gagal', 'fail');
                    return null;
                }
                return mtApi('/noc/features/map/mikrotik/connect', 'POST', { id: r.data.router.id });
            }).then(function(r) {
                if (!r) return;
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || 'Konek gagal', 'error');
                    setMtStatus(r.data.error || 'Konek gagal', 'fail');
                } else {
                    var ppp = r.data.pppoe_users;
                    var msg = 'Konek OK' + (ppp != null ? ' — ' + ppp + ' user PPPoE' : (r.data.routeros_version ? ' — v' + r.data.routeros_version : ''));
                    ftthToast(msg, 'ok');
                    setMtStatus(msg, 'ok');
                    updatePppoeStats(r.data.pppoe_online, r.data.pppoe_offline, r.data.prev_pppoe_online, r.data.prev_pppoe_offline);
                }
                return loadRouterList();
            }).catch(function() {
                ftthToast('Konek gagal', 'error');
                setMtStatus('Konek gagal', 'fail');
            }).then(function() { setMtBusy(false); });
        };

        window.ftthSyncAllMikrotik = function() {
            if (mtBusy) return;
            setMtBusy(true);
            setMtStatus('Menyinkronkan semua...', 'info', true);
            mtApi('/noc/features/map/mikrotik/sync-all', 'POST').then(function(r) {
                if (r.data.ok != null) {
                    if (r.data.failed) {
                        ftthToast('Sync ' + r.data.ok + '/' + r.data.total + ' router berhasil', 'warn');
                        setMtStatus('Gagal ' + r.data.failed + '/' + r.data.total, 'fail');
                    } else {
                        ftthToast('Sync ' + r.data.ok + '/' + r.data.total + ' router berhasil', 'ok');
                        setMtStatus('Sync ' + r.data.ok + '/' + r.data.total + ' OK', 'ok');
                    }
                    setPppoeStats(r.data.pppoe_online, r.data.pppoe_offline);
                } else {
                    ftthToast(r.data.error || 'Gagal sync', 'error');
                    setMtStatus(r.data.error || 'Gagal sync', 'fail');
                }
                loadRouterList();
            }).catch(function() {
                ftthToast('Gagal sync semua router', 'error');
                setMtStatus('Gagal sync', 'fail');
            }).then(function() { setMtBusy(false); });
        };

        window.ftthSyncRouter = function(id) {
            if (mtBusy) return;
            setMtBusy(true);
            setMtStatus('Menghubungkan...', 'info', true);
            mtApi('/noc/features/map/mikrotik/connect', 'POST', { id: id }).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || 'Sync router gagal', 'error');
                    setMtStatus(r.data.error || 'Sync gagal', 'fail');
                } else {
                    var ppp = r.data.pppoe_users;
                    var msg = 'Sync OK' + (ppp != null ? ' — ' + ppp + ' user PPPoE' : (r.data.routeros_version ? ' — v' + r.data.routeros_version : ''));
                    ftthToast(msg, 'ok');
                    setMtStatus(msg, 'ok');
                    updatePppoeStats(r.data.pppoe_online, r.data.pppoe_offline, r.data.prev_pppoe_online, r.data.prev_pppoe_offline);
                }
                loadRouterList();
            }).catch(function() {
                ftthToast('Sync router gagal', 'error');
                setMtStatus('Sync gagal', 'fail');
            }).then(function() { setMtBusy(false); });
        };

        window.ftthDelRouter = function(id) {
            if (mtBusy) return;
            if (!confirm('Hapus router ini?')) return;
            setMtBusy(true);
            setMtStatus('Menghapus...', 'info', true);
            mtApi('/noc/features/map/mikrotik/delete', 'POST', { id: id }).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || 'Gagal hapus router', 'error');
                    setMtStatus(r.data.error || 'Gagal hapus', 'fail');
                } else {
                    ftthToast('Router dihapus', 'ok');
                    setMtStatus('Router dihapus', 'ok');
                }
                renderRouterList((r.data.routers || []).length ? r.data.routers : []);
            }).catch(function() {
                ftthToast('Gagal hapus router', 'error');
                setMtStatus('Gagal hapus', 'fail');
            }).then(function() { setMtBusy(false); });
        };

        /* ── Sync OLT ── */

        function setOltStatus(msg, type, spin) {
            var s = document.getElementById('ftthOltStatus');
            if (!s) return;
            s.className = 'ftth-mt-status ' + (type || 'info');
            s.innerHTML = (spin ? '<i class="fa-solid fa-spinner ftth-spin"></i> ' : '') + escapeHtml(msg);
        }

        function updateOnuStats(online, offline) {
            var a = document.getElementById('ftthOnuOnline');
            var b = document.getElementById('ftthOnuOffline');
            if (a && online != null) a.textContent = online;
            if (b && offline != null) b.textContent = offline;
        }

        function setPppoeStats(online, offline) {
            var a = document.getElementById('ftthPppoeOnline');
            var b = document.getElementById('ftthPppoeOffline');
            if (a && online != null) a.textContent = online;
            if (b && offline != null) b.textContent = offline;
        }

        function updatePppoeStats(online, offline, prevOnline, prevOffline) {
            var a = document.getElementById('ftthPppoeOnline');
            var b = document.getElementById('ftthPppoeOffline');
            if (a && online != null) {
                var cur = parseInt(a.textContent, 10) || 0;
                a.textContent = cur + online - (prevOnline != null ? prevOnline : cur);
            }
            if (b && offline != null) {
                var curO = parseInt(b.textContent, 10) || 0;
                b.textContent = curO + offline - (prevOffline != null ? prevOffline : curO);
            }
        }

        window.ftthOpenOlt = function() {
            var bd = document.getElementById('ftthOltBackdrop');
            bd.hidden = false;
            positionCardBelow(document.getElementById('ftthOltCard'), document.querySelector('.ftth-ac-olt'));
            loadOltList();
        };

        window.ftthCloseOlt = function() {
            document.getElementById('ftthOltBackdrop').hidden = true;
        };

        function oltReadForm() {
            var ip = document.getElementById('oltIp').value.trim();
            var port = document.getElementById('oltPort').value.trim();
            var user = document.getElementById('oltUser').value.trim();
            var pass = document.getElementById('oltPass').value;
            var brand = document.getElementById('oltBrand').value;
            if (!ip) { ftthToast('Isi IP OLT', 'warn'); return null; }
            if (!port || isNaN(parseInt(port, 10))) { ftthToast('Isi port SSH', 'warn'); return null; }
            if (!user) { ftthToast('Isi username', 'warn'); return null; }
            if (!pass) { ftthToast('Isi password', 'warn'); return null; }
            return { ip: ip, port: parseInt(port, 10), username: user, password: pass, brand: brand };
        }

        function renderOltList(olts) {
            var list = document.getElementById('ftthOltList');
            if (!olts.length) {
                list.innerHTML = '<div class="ftth-router-empty">Belum ada OLT tersimpan</div>';
                return;
            }
            list.innerHTML = olts.map(function(o) {
                var dotColor = o.status === 'online' ? '#22c55e' : o.status === 'offline' ? '#ef4444' : '#94a3b8';
                var sub = escapeHtml(o.brand || '') + (o.model ? ' · ' + escapeHtml(o.model) : '');
                return '<div class="ftth-router-row" data-id="' + o.id + '">' +
                    '<span class="ftth-router-info">' +
                        '<span class="ftth-router-line"><span class="dot" style="background:' + dotColor + '"></span>' + escapeHtml(o.ip) + ' : ' + escapeHtml(o.port) + '</span>' +
                        '<span class="ftth-router-version">' + (sub || 'v?') + '</span>' +
                    '</span>' +
                    '<span class="ftth-router-actions">' +
                        '<button type="button" class="ftth-row-btn sync" onclick="ftthSyncOlt(' + o.id + ')"><i class="fa-solid fa-rotate"></i> Sync</button>' +
                        '<button type="button" class="ftth-row-btn del" onclick="ftthDelOlt(' + o.id + ')"><i class="fa-solid fa-trash"></i> Del</button>' +
                    '</span>' +
                '</div>';
            }).join('');
        }

        function loadOltList() {
            var list = document.getElementById('ftthOltList');
            if (!list) return;
            list.innerHTML = '<div class="ftth-router-empty"><i class="fa-solid fa-spinner ftth-spin"></i> Memuat...</div>';
            mtApi('/noc/features/map/olt', 'GET').then(function(r) {
                renderOltList(r.data.olts || []);
            }).catch(function() {
                list.innerHTML = '<div class="ftth-router-empty">Gagal memuat daftar OLT</div>';
            });
        }

        window.ftthSaveOlt = function() {
            if (oltBusy) return;
            var payload = oltReadForm();
            if (!payload) return;
            setOltBusy(true);
            setOltStatus('Menyimpan...', 'info', true);
            mtApi('/noc/features/map/olt/save', 'POST', payload).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || 'Gagal menyimpan OLT', 'error');
                    setOltStatus(r.data.error || 'Gagal simpan', 'fail');
                    return;
                }
                ftthToast('OLT tersimpan', 'ok');
                setOltStatus('Tersimpan', 'ok');
                renderOltList(r.data.olts || []);
            }).catch(function() {
                ftthToast('Gagal menyimpan OLT', 'error');
                setOltStatus('Gagal simpan', 'fail');
            }).then(function() { setOltBusy(false); });
        };

        window.ftthConnectOlt = function() {
            if (oltBusy) return;
            var payload = oltReadForm();
            if (!payload) return;
            setOltBusy(true);
            setOltStatus('Menghubungkan...', 'info', true);
            mtApi('/noc/features/map/olt/save', 'POST', payload).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || 'Simpan OLT dulu untuk konek', 'error');
                    setOltStatus(r.data.error || 'Konek gagal', 'fail');
                    return null;
                }
                return mtApi('/noc/features/map/olt/connect', 'POST', { id: r.data.olt.id });
            }).then(function(r) {
                if (!r) return;
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || 'Konek gagal', 'error');
                    setOltStatus(r.data.error || 'Konek gagal', 'fail');
                } else {
                    var onu = r.data.onu_total;
                    var msg = 'Konek OK' + (onu != null ? ' — ' + onu + ' ONU' : '');
                    ftthToast(msg, 'ok');
                    setOltStatus(msg, 'ok');
                    updateOnuStats(r.data.onu_online, r.data.onu_offline);
                }
                return loadOltList();
            }).catch(function() {
                ftthToast('Konek gagal', 'error');
                setOltStatus('Konek gagal', 'fail');
            }).then(function() { setOltBusy(false); });
        };

        window.ftthSyncAllOlt = function() {
            if (oltBusy) return;
            setOltBusy(true);
            setOltStatus('Menyinkronkan semua...', 'info', true);
            mtApi('/noc/features/map/olt/sync-all', 'POST').then(function(r) {
                if (r.data.ok != null) {
                    if (r.data.failed) {
                        ftthToast('Sync ' + r.data.ok + '/' + r.data.total + ' OLT berhasil', 'warn');
                        setOltStatus('Gagal ' + r.data.failed + '/' + r.data.total, 'fail');
                    } else {
                        ftthToast('Sync ' + r.data.ok + '/' + r.data.total + ' OLT berhasil', 'ok');
                        setOltStatus('Sync ' + r.data.ok + '/' + r.data.total + ' OK', 'ok');
                    }
                } else {
                    ftthToast(r.data.error || 'Gagal sync', 'error');
                    setOltStatus(r.data.error || 'Gagal sync', 'fail');
                }
                loadOltList();
            }).catch(function() {
                ftthToast('Gagal sync semua OLT', 'error');
                setOltStatus('Gagal sync', 'fail');
            }).then(function() { setOltBusy(false); });
        };

        window.ftthSyncOlt = function(id) {
            if (oltBusy) return;
            setOltBusy(true);
            setOltStatus('Menghubungkan...', 'info', true);
            mtApi('/noc/features/map/olt/connect', 'POST', { id: id }).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || 'Sync OLT gagal', 'error');
                    setOltStatus(r.data.error || 'Sync gagal', 'fail');
                } else {
                    var onu = r.data.onu_total;
                    var msg = 'Sync OK' + (onu != null ? ' — ' + onu + ' ONU' : '');
                    ftthToast(msg, 'ok');
                    setOltStatus(msg, 'ok');
                    updateOnuStats(r.data.onu_online, r.data.onu_offline);
                }
                loadOltList();
            }).catch(function() {
                ftthToast('Sync OLT gagal', 'error');
                setOltStatus('Sync gagal', 'fail');
            }).then(function() { setOltBusy(false); });
        };

        window.ftthDelOlt = function(id) {
            if (oltBusy) return;
            if (!confirm('Hapus OLT ini?')) return;
            setOltBusy(true);
            setOltStatus('Menghapus...', 'info', true);
            mtApi('/noc/features/map/olt/delete', 'POST', { id: id }).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || 'Gagal hapus OLT', 'error');
                    setOltStatus(r.data.error || 'Gagal hapus', 'fail');
                } else {
                    ftthToast('OLT dihapus', 'ok');
                    setOltStatus('OLT dihapus', 'ok');
                }
                renderOltList((r.data.olts || []).length ? r.data.olts : []);
            }).catch(function() {
                ftthToast('Gagal hapus OLT', 'error');
                setOltStatus('Gagal hapus', 'fail');
            }).then(function() { setOltBusy(false); });
        };

        /* ── Sync GenieACS ── */

        function setGenieacsStatus(msg, type, spin) {
            var s = document.getElementById('ftthGenieacsStatus');
            if (!s) return;
            s.className = 'ftth-mt-status ' + (type || 'info');
            s.innerHTML = (spin ? '<i class="fa-solid fa-spinner ftth-spin"></i> ' : '') + escapeHtml(msg);
        }

        window.ftthOpenGenieacs = function() {
            var bd = document.getElementById('ftthGenieacsBackdrop');
            bd.hidden = false;
            positionCardBelow(document.getElementById('ftthGenieacsCard'), document.querySelector('.ftth-ac-genieacs'));
            loadGenieacsConfig();
        };

        window.ftthCloseGenieacs = function() {
            document.getElementById('ftthGenieacsBackdrop').hidden = true;
        };

        function loadGenieacsConfig() {
            var input = document.getElementById('genieacsUrl');
            mtApi('/noc/features/map/genieacs', 'GET').then(function(r) {
                if (input && r.data.base_url) input.value = r.data.base_url;
            }).catch(function() {});
        }

        window.ftthSaveGenieacsConfig = function() {
            var url = document.getElementById('genieacsUrl').value.trim();
            setGenieacsStatus('Menyimpan...', 'info', true);
            mtApi('/noc/features/map/genieacs/save', 'POST', { url: url }).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || 'Gagal simpan config', 'error');
                    setGenieacsStatus(r.data.error || 'Gagal simpan', 'fail');
                } else {
                    ftthToast(r.data.message || 'Config tersimpan', 'ok');
                    setGenieacsStatus('Config tersimpan', 'ok');
                }
            }).catch(function() {
                ftthToast('Gagal simpan config', 'error');
                setGenieacsStatus('Gagal simpan', 'fail');
            });
        };

        window.ftthSyncGenieacsDevices = function() {
            setGenieacsStatus('Menyinkronkan...', 'info', true);
            mtApi('/noc/features/map/genieacs/sync', 'POST').then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || 'Gagal sync GenieACS', 'error');
                    setGenieacsStatus(r.data.error || 'Gagal sync', 'fail');
                } else {
                    var msg = r.data.message || (r.data.total + ' device');
                    ftthToast(msg, 'ok');
                    setGenieacsStatus(r.data.online + ' online · ' + r.data.offline + ' offline', 'ok');
                }
                renderGenieacsSummary(r.data);
            }).catch(function() {
                ftthToast('Gagal sync GenieACS', 'error');
                setGenieacsStatus('Gagal sync', 'fail');
            });
        };

        function renderGenieacsSummary(d) {
            var el = document.getElementById('ftthGenieacsSummary');
            if (!el) return;
            if (!d || !d.ok) {
                el.innerHTML = '<div class="ftth-router-empty">Belum ada hasil sync</div>';
                return;
            }
            el.innerHTML =
                '<div class="ftth-router-row"><span class="ftth-router-info">' +
                    '<span class="ftth-router-line"><span class="dot" style="background:#22c55e"></span> Online</span>' +
                    '<span class="ftth-router-version">GenieACS device aktif</span>' +
                '</span><b style="color:#4ade80">' + (d.online || 0) + '</b></div>' +
                '<div class="ftth-router-row"><span class="ftth-router-info">' +
                    '<span class="ftth-router-line"><span class="dot" style="background:#ef4444"></span> Offline</span>' +
                    '<span class="ftth-router-version">GenieACS device tidak aktif</span>' +
                '</span><b style="color:#f87171">' + (d.offline || 0) + '</b></div>' +
                '<div class="ftth-router-row"><span class="ftth-router-info">' +
                    '<span class="ftth-router-line">Total device</span>' +
                    '<span class="ftth-router-version">' + (d.updated || 0) + ' ONU tersambung</span>' +
                '</span><b style="color:#93c5fd">' + (d.total || 0) + '</b></div>';
        }

        /* ── Notifikasi (WhatsApp & Telegram) ── */

        var notifConfigCache = null;

        function setNotifStatus(id, msg, type, spin) {
            var s = document.getElementById(id);
            if (!s) return;
            s.className = 'ftth-mt-status ' + (type || 'info');
            s.innerHTML = (spin ? '<i class="fa-solid fa-spinner ftth-spin"></i> ' : '') + escapeHtml(msg);
        }

        function loadNotifConfig() {
            if (notifConfigCache) return Promise.resolve(notifConfigCache);
            return mtApi('/noc/features/map/notif/config', 'GET').then(function(r) {
                if (r.data && r.data.ok) notifConfigCache = r.data;
                return notifConfigCache || { wa: {}, telegram: {} };
            }).catch(function() { return { wa: {}, telegram: {} }; });
        }

        function fillNotifWa(d) {
            d = d.wa || {};
            document.getElementById('notifWaEnabled').checked = d.enabled === 'true';
            document.getElementById('notifWaUrl').value = d.api_url || '';
            document.getElementById('notifWaKey').value = d.api_key || '';
            document.getElementById('notifWaSender').value = d.sender || '';
            document.getElementById('notifWaRecipient').value = d.recipient || '';
        }

        function fillNotifTg(d) {
            d = d.telegram || {};
            document.getElementById('notifTgEnabled').checked = d.enabled === 'true';
            document.getElementById('notifTgToken').value = d.bot_token || '';
            document.getElementById('notifTgChatId').value = d.chat_id || '';
        }

        window.ftthOpenNotifWa = function() {
            notifMenuOpen = false;
            var menu = document.getElementById('ftthNotifMenu');
            if (menu) menu.classList.remove('open');
            notifBtnState();
            document.getElementById('ftthNotifWaBackdrop').hidden = false;
            positionCardBelow(document.getElementById('ftthNotifWaCard'), document.getElementById('ftthNotifBtn'));
            setNotifStatus('ftthNotifWaStatus', '', 'info');
            loadNotifConfig().then(fillNotifWa);
        };

        window.ftthCloseNotifWa = function() {
            document.getElementById('ftthNotifWaBackdrop').hidden = true;
        };

        window.ftthOpenNotifTg = function() {
            notifMenuOpen = false;
            var menu = document.getElementById('ftthNotifMenu');
            if (menu) menu.classList.remove('open');
            notifBtnState();
            document.getElementById('ftthNotifTgBackdrop').hidden = false;
            positionCardBelow(document.getElementById('ftthNotifTgCard'), document.getElementById('ftthNotifBtn'));
            setNotifStatus('ftthNotifTgStatus', '', 'info');
            loadNotifConfig().then(fillNotifTg);
        };

        window.ftthCloseNotifTg = function() {
            document.getElementById('ftthNotifTgBackdrop').hidden = true;
        };

        window.ftthSaveNotifWa = function() {
            setNotifStatus('ftthNotifWaStatus', 'Menyimpan...', 'info', true);
            mtApi('/noc/features/map/notif/save', 'POST', {
                wa: {
                    enabled: String(document.getElementById('notifWaEnabled').checked),
                    api_url: document.getElementById('notifWaUrl').value.trim(),
                    api_key: document.getElementById('notifWaKey').value.trim(),
                    sender: document.getElementById('notifWaSender').value.trim(),
                    recipient: document.getElementById('notifWaRecipient').value.trim()
                }
            }).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    var err = r.data.error || 'Gagal simpan pengaturan';
                    ftthToast(err, 'error');
                    setNotifStatus('ftthNotifWaStatus', err, 'fail');
                } else {
                    notifConfigCache = null;
                    ftthToast('Pengaturan WhatsApp tersimpan', 'ok');
                    setNotifStatus('ftthNotifWaStatus', 'Tersimpan', 'ok');
                }
            }).catch(function() {
                ftthToast('Gagal simpan pengaturan', 'error');
                setNotifStatus('ftthNotifWaStatus', 'Gagal simpan', 'fail');
            });
        };

        window.ftthSaveNotifTg = function() {
            setNotifStatus('ftthNotifTgStatus', 'Menyimpan...', 'info', true);
            mtApi('/noc/features/map/notif/save', 'POST', {
                telegram: {
                    enabled: String(document.getElementById('notifTgEnabled').checked),
                    bot_token: document.getElementById('notifTgToken').value.trim(),
                    chat_id: document.getElementById('notifTgChatId').value.trim()
                }
            }).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    var err = r.data.error || 'Gagal simpan pengaturan';
                    ftthToast(err, 'error');
                    setNotifStatus('ftthNotifTgStatus', err, 'fail');
                } else {
                    notifConfigCache = null;
                    ftthToast('Pengaturan Telegram tersimpan', 'ok');
                    setNotifStatus('ftthNotifTgStatus', 'Tersimpan', 'ok');
                }
            }).catch(function() {
                ftthToast('Gagal simpan pengaturan', 'error');
                setNotifStatus('ftthNotifTgStatus', 'Gagal simpan', 'fail');
            });
        };

        /* ── Card Queue (PPPoE client) ── */

        var queueData = [];

        function setQueueStatus(msg, type, spin) {
            var s = document.getElementById('ftthQueueStatus');
            if (!s) return;
            s.className = 'ftth-mt-status ' + (type || 'info');
            s.innerHTML = (spin ? '<i class="fa-solid fa-spinner ftth-spin"></i> ' : '') + escapeHtml(msg);
        }

        window.ftthOpenQueue = function() {
            var bd = document.getElementById('ftthQueueBackdrop');
            bd.hidden = false;
            positionCardBelow(document.getElementById('ftthQueueCard'), document.querySelector('.ftth-ac-queue'));
            loadQueue();
        };

        window.ftthCloseQueue = function() {
            document.getElementById('ftthQueueBackdrop').hidden = true;
        };

        window.ftthRefreshQueue = function() { loadQueue(); };

        function loadQueue() {
            var wrap = document.getElementById('ftthQueueWrap');
            if (!wrap) return;
            wrap.innerHTML = '<div class="ftth-router-empty"><i class="fa-solid fa-spinner ftth-spin"></i> Mengambil data PPPoE client...</div>';
            setQueueStatus('Memuat...', 'info', true);
            mtApi('/noc/features/map/mikrotik/pppoe', 'GET').then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    setQueueStatus(r.data.error || 'Gagal memuat data', 'fail');
                    wrap.innerHTML = '<div class="ftth-router-empty">' + escapeHtml(r.data.error || 'Gagal memuat data PPPoE client') + '</div>';
                    return;
                }
                queueData = r.data.clients || [];
                renderQueue();
                setQueueStatus(queueData.length + ' client aktif', 'ok');
                ftthToast(queueData.length + ' data PPPoE client tersedia', 'ok');
            }).catch(function() {
                setQueueStatus('Gagal memuat', 'fail');
                wrap.innerHTML = '<div class="ftth-router-empty">Gagal memuat data PPPoE client</div>';
            });
        }

        function renderQueue() {
            var wrap = document.getElementById('ftthQueueWrap');
            if (!wrap) return;
            if (!queueData.length) {
                wrap.innerHTML = '<div class="ftth-router-empty">Tidak ada PPPoE client aktif.<br><small>Pastikan router sudah tersimpan & disinkronkan.</small></div>';
                return;
            }
            var q = ((document.getElementById('ftthQueueSearch') || {}).value || '').toLowerCase();
            var rows = [];
            queueData.forEach(function(c, i) {
                if (!q || (c.name + ' ' + c.address + ' ' + c.router_name + ' ' + c.profile + ' ' + c.comment).toLowerCase().indexOf(q) !== -1) {
                    rows.push({ c: c, i: i });
                }
            });
            if (!rows.length) {
                wrap.innerHTML = '<div class="ftth-router-empty">Tidak ada hasil untuk pencarian</div>';
                return;
            }
            var html = '<table class="ftth-queue-table"><thead><tr>' +
                '<th>Router</th><th>User</th><th>Alamat IP</th><th>Uptime</th><th>Profile</th><th>Aksi</th>' +
                '</tr></thead><tbody>' + rows.map(function(row) {
                    var c = row.c;
                    return '<tr>' +
                        '<td class="ftth-q-router">' + escapeHtml(c.router_name) + '</td>' +
                        '<td class="ftth-q-user">' + escapeHtml(c.name) + '</td>' +
                        '<td>' + escapeHtml(c.address || '-') + '</td>' +
                        '<td>' + escapeHtml(c.uptime || '-') + '</td>' +
                        '<td>' + escapeHtml(c.profile || '-') + '</td>' +
                        '<td class="ftth-q-actions"><button type="button" class="ftth-row-btn add" onclick="ftthQueueAdd(' + row.i + ')"><i class="fa-solid fa-plus"></i> ADD</button></td>' +
                        '</tr>';
                }).join('') + '</tbody></table>';
            wrap.innerHTML = html;
        }

        window.ftthQueueAdd = function(idx) {
            var c = queueData[idx];
            if (!c) return;
            ftthOpenAddDevice({
                type: 'onu',
                name: c.name || '',
                ip: c.address || '',
                pppoe: c.name || '',
                parent: c.router_name || '',
                notes: [c.comment, c.profile ? 'Profile: ' + c.profile : ''].filter(Boolean).join(' — ')
            });
        };

        var queueSearchEl = document.getElementById('ftthQueueSearch');
        if (queueSearchEl) {
            queueSearchEl.addEventListener('input', function() {
                if (queueData.length) renderQueue();
            });
        }

        /* ── Card Backup & Restore ── */

        var backupBusy = false;

        function setBackupStatus(msg, type, spin) {
            var s = document.getElementById('ftthBackupStatus');
            if (!s) return;
            s.className = 'ftth-mt-status ' + (type || 'info');
            s.innerHTML = (spin ? '<i class="fa-solid fa-spinner ftth-spin"></i> ' : '') + escapeHtml(msg);
        }

        function setBackupBusy(b) {
            backupBusy = b;
            var card = document.getElementById('ftthBackupCard');
            if (!card) return;
            card.querySelectorAll('.ftth-backup-btn').forEach(function(x) { x.disabled = b; });
        }

        function mtUpload(path, file, extra) {
            var fd = new FormData();
            fd.append('file', file);
            if (extra) {
                Object.keys(extra).forEach(function(k) { fd.append(k, extra[k]); });
            }
            return fetch(path, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                body: fd
            }).then(function(res) {
                return res.json().catch(function() { return {}; }).then(function(d) {
                    return { status: res.status, data: d };
                });
            });
        }

        window.ftthOpenBackup = function() {
            var bd = document.getElementById('ftthBackupBackdrop');
            bd.hidden = false;
            positionCardBelow(document.getElementById('ftthBackupCard'), document.querySelector('.ftth-ac-backup'));
            loadBackupConfig();
        };

        window.ftthCloseBackup = function() {
            document.getElementById('ftthBackupBackdrop').hidden = true;
        };

        function loadBackupConfig() {
            mtApi('/noc/features/map/backup/config', 'GET').then(function(r) {
                if (r.data.ok) {
                    var e = document.getElementById('backupEmail');
                    var t = document.getElementById('backupTime');
                    if (e && r.data.backup_email) e.value = r.data.backup_email;
                    if (t && r.data.backup_time) t.value = r.data.backup_time;
                }
            }).catch(function() {});
        }

        window.ftthSaveBackup = function() {
            var email = document.getElementById('backupEmail').value.trim();
            var time = document.getElementById('backupTime').value.trim();
            setBackupBusy(true);
            setBackupStatus('Menyimpan...', 'info', true);
            mtApi('/noc/features/map/backup/save', 'POST', { email: email, time: time }).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || 'Gagal simpan konfigurasi', 'error');
                    setBackupStatus(r.data.error || 'Gagal simpan', 'fail');
                } else {
                    ftthToast(r.data.message || 'Konfigurasi tersimpan', 'ok');
                    setBackupStatus('Config tersimpan', 'ok');
                }
            }).catch(function() {
                ftthToast('Gagal simpan konfigurasi', 'error');
                setBackupStatus('Gagal simpan', 'fail');
            }).then(function() { setBackupBusy(false); });
        };

        window.ftthSendBackupNow = function() {
            var email = document.getElementById('backupEmail').value.trim();
            setBackupBusy(true);
            setBackupStatus('Menyiapkan backup & mengirim...', 'info', true);
            mtApi('/noc/features/map/backup/send', 'POST', { email: email }).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || 'Gagal kirim backup', 'error');
                    setBackupStatus(r.data.error || 'Gagal kirim', 'fail');
                } else {
                    ftthToast(r.data.message || 'Backup terkirim', 'ok');
                    setBackupStatus('Terkirim '.concat(r.data.filename || ''), 'ok');
                }
            }).catch(function() {
                ftthToast('Gagal kirim backup', 'error');
                setBackupStatus('Gagal kirim', 'fail');
            }).then(function() { setBackupBusy(false); });
        };

        window.ftthRestoreFile = function(kind) {
            var input = document.getElementById('ftthRestoreFile');
            input.dataset.kind = kind;
            input.click();
        };

        var restoreFileEl = document.getElementById('ftthRestoreFile');
        if (restoreFileEl) {
            restoreFileEl.addEventListener('change', function() {
                var f = restoreFileEl.files[0];
                if (!f) return;
                setBackupBusy(true);
                setBackupStatus('Memulihkan...', 'info', true);
                mtUpload('/noc/features/map/backup/restore', f, { kind: restoreFileEl.dataset.kind || 'database' }).then(function(r) {
                    if (r.status >= 400 || !r.data.ok) {
                        ftthToast(r.data.error || 'Gagal restore', 'error');
                        setBackupStatus(r.data.error || 'Gagal restore', 'fail');
                    } else {
                        ftthToast(r.data.message || 'Restore selesai', 'ok');
                        setBackupStatus('Restore selesai', 'ok');
                    }
                }).catch(function() {
                    ftthToast('Gagal restore', 'error');
                    setBackupStatus('Gagal restore', 'fail');
                }).then(function() {
                    setBackupBusy(false);
                    restoreFileEl.value = '';
                });
            });
        }

        window.ftthImportExcel = function() { document.getElementById('ftthExcelFile').click(); };
        window.ftthExportExcel = function() { window.open('/noc/features/map/backup/excel-export', '_blank'); };

        var excelFileEl = document.getElementById('ftthExcelFile');
        if (excelFileEl) {
            excelFileEl.addEventListener('change', function() {
                var f = excelFileEl.files[0];
                if (!f) return;
                setBackupBusy(true);
                setBackupStatus('Mengimpor data Excel...', 'info', true);
                mtUpload('/noc/features/map/backup/excel-import', f).then(function(r) {
                    if (r.status >= 400 || !r.data.ok) {
                        ftthToast(r.data.error || 'Gagal import', 'error');
                        setBackupStatus(r.data.error || 'Gagal import', 'fail');
                    } else {
                        ftthToast(r.data.message || 'Import selesai', 'ok');
                        setBackupStatus('Import selesai', 'ok');
                    }
                }).catch(function() {
                    ftthToast('Gagal import', 'error');
                    setBackupStatus('Gagal import', 'fail');
                }).then(function() {
                    setBackupBusy(false);
                    excelFileEl.value = '';
                });
            });
        }

        window.ftthImportKmz = function() { document.getElementById('ftthKmzFile').click(); };
        window.ftthExportKmz = function() { window.open('/noc/features/map/backup/kmz-export', '_blank'); };

        var kmzFileEl = document.getElementById('ftthKmzFile');
        if (kmzFileEl) {
            kmzFileEl.addEventListener('change', function() {
                var f = kmzFileEl.files[0];
                if (!f) return;
                setBackupBusy(true);
                setBackupStatus('Mengimpor KML/KMZ...', 'info', true);
                mtUpload('/noc/features/map/backup/kmz-import', f).then(function(r) {
                    if (r.status >= 400 || !r.data.ok) {
                        ftthToast(r.data.error || 'Gagal import', 'error');
                        setBackupStatus(r.data.error || 'Gagal import', 'fail');
                    } else {
                        ftthToast(r.data.message || 'Import selesai', 'ok');
                        setBackupStatus('Import selesai', 'ok');
                    }
                }).catch(function() {
                    ftthToast('Gagal import', 'error');
                    setBackupStatus('Gagal import', 'fail');
                }).then(function() {
                    setBackupBusy(false);
                    kmzFileEl.value = '';
                });
            });
        }

        /* ── Tambah Perangkat, Perangkat, Tabel ONU, Marker peta ── */

        var ftthCoreColors = [
            ['Hijau', '#16a34a'], ['Merah', '#dc2626'], ['Biru', '#2563eb'],
            ['Kuning', '#eab308'], ['Putih', '#f1f5f9'], ['Hitam', '#1f2937'],
            ['Orange', '#ea580c'], ['Ungu', '#7c3aed'], ['Abu-abu', '#9ca3af'],
            ['Coklat', '#92400e'], ['Pink', '#ec4899'], ['Tosca', '#14b8a6']
        ];

        function ftthCoreColorSelects() {
            var ponOpts = '';
            for (var p = 1; p <= 16; p++) ponOpts += '<option value="PON ' + p + '">PON ' + p + '</option>';
            var colOpts = ftthCoreColors.map(function(c) {
                return '<option value="' + c[1] + '" style="color:' + c[1] + '">' + c[0] + '</option>';
            }).join('');
            return '<div class="ftth-df"><label>Nomor PON</label><select id="ftthDevPonNo">' + ponOpts + '</select></div>' +
                '<div class="ftth-df"><label>Warna Core</label><select id="ftthDevCoreColor">' + colOpts + '</select></div>';
        }

        var deviceTypeColors = {
            'ONU': '#8b5cf6', 'ODP': '#ef4444', 'HTB': '#14b8a6', 'CLOSURE': '#eab308',
            'ODC': '#f59e0b', 'OTB': '#ec4899', 'OLT': '#3b82f6', 'CUSTOM': '#94a3b8',
            'ROUTER': '#34d399', 'CUSTOMER': '#22c55e'
        };

        function ftthDeviceColor(type) {
            return deviceTypeColors[String(type).toUpperCase()] || '#94a3b8';
        }

        function ftthRenderDeviceFields() {
            var type = document.getElementById('ftthDeviceType').value;
            var extra = document.getElementById('ftthDevExtra');
            var coreChk = document.getElementById('ftthCoreChkWrap');
            var coreFields = document.getElementById('ftthCoreFields');
            var coreMgmt = document.getElementById('ftthDevCoreMgmt');
            var coreOn = coreMgmt.checked;

            if (type === 'odc' || type === 'odp') {
                extra.innerHTML = '<div class="ftth-df"><label>Jumlah Kapasitas Port</label>' +
                    '<input type="text" id="ftthDevCapacity" placeholder="e.g. 288 / 16" autocomplete="off"></div>';
            } else if (type === 'onu') {
                extra.innerHTML = '<div class="ftth-df"><label>IP Address</label>' +
                    '<input type="text" id="ftthDevIp" placeholder="e.g. 192.168.1.5" autocomplete="off"></div>' +
                    '<div class="ftth-df"><label>User PPPoE</label>' +
                    '<input type="text" id="ftthDevPppoe" placeholder="e.g. alk-001" autocomplete="off"></div>';
            } else {
                extra.innerHTML = '';
            }

            var canCore = (type === 'olt' || type === 'odc' || type === 'odp');
            coreChk.hidden = !canCore;
            if (!canCore) coreMgmt.checked = false;

            if (canCore && coreOn) {
                if (type === 'olt') {
                    coreFields.innerHTML = '<div class="ftth-df"><label>Jumlah PON</label>' +
                        '<input type="text" id="ftthDevPonCount" placeholder="e.g. 8" autocomplete="off"></div>';
                } else {
                    coreFields.innerHTML = ftthCoreColorSelects();
                }
                coreFields.hidden = false;
            } else {
                coreFields.hidden = true;
                coreFields.innerHTML = '';
            }
        }

        var ftthParentsLoaded = false;
        var ftthEditDeviceId = null;

        function loadDeviceParents(cb) {
            var sel = document.getElementById('ftthDevParent');
            if (ftthParentsLoaded) {
                if (cb) cb();
                return;
            }
            mtApi('/noc/features/map/device/parents', 'GET').then(function(r) {
                if (r.data && r.data.ok && r.data.parents) {
                    sel.innerHTML = '<option value="">— Pilih Induk —</option>' + r.data.parents.map(function(p) {
                        return '<option value="' + escapeHtml(p.type + ' — ' + p.name) + '">' + escapeHtml(p.type + ' — ' + p.name) + '</option>';
                    }).join('');
                    ftthParentsLoaded = true;
                }
                if (cb) cb();
            }).catch(function() {
                if (cb) cb();
            });
        }

        function ftthOpenAddDevice(prefill) {
            prefill = prefill || {};
            ftthCloseDetail();
            ftthEditDeviceId = prefill.id || null;
            var isEdit = !!ftthEditDeviceId;
            document.getElementById('ftthDeviceType').value = prefill.type || '';
            document.getElementById('ftthDevName').value = prefill.name || '';
            document.getElementById('ftthDevNotes').value = prefill.notes || '';
            document.getElementById('ftthDevLat').value = '';
            document.getElementById('ftthDevLng').value = '';
            document.getElementById('ftthDevLocation').value = '';
            document.getElementById('ftthDevCoreMgmt').checked = !!prefill.management_core;
            document.getElementById('ftthAddDeviceTitle').innerHTML =
                '<i class="fa-solid ' + (isEdit ? 'fa-pen' : 'fa-plus') + '"></i> ' +
                (isEdit ? 'Edit Perangkat' : (prefill.type === 'onu' ? 'Tambah Perangkat — ONU' : 'Tambah Perangkat'));
            ftthRenderDeviceFields();

            if (prefill.capacity) {
                var cap = document.getElementById('ftthDevCapacity');
                if (cap) cap.value = prefill.capacity;
            }
            if (prefill.ip) {
                var ip = document.getElementById('ftthDevIp');
                if (ip) ip.value = prefill.ip;
            }
            if (prefill.pppoe) {
                var pp = document.getElementById('ftthDevPppoe');
                if (pp) pp.value = prefill.pppoe;
            }
            if (prefill.jumlah_pon) {
                var pc = document.getElementById('ftthDevPonCount');
                if (pc) pc.value = prefill.jumlah_pon;
            }
            if (prefill.nomor_pon) {
                var pn = document.getElementById('ftthDevPonNo');
                if (pn) pn.value = prefill.nomor_pon;
            }
            if (prefill.warna_core) {
                var cc = document.getElementById('ftthDevCoreColor');
                if (cc) cc.value = prefill.warna_core;
            }

            loadDeviceParents(function() {
                if (!prefill.parent) return;
                var sel = document.getElementById('ftthDevParent');
                var match = null;
                Array.from(sel.options).forEach(function(o) {
                    if (!o.value) return;
                    if (o.text === prefill.parent || o.text.indexOf(prefill.parent) !== -1) match = o.value;
                });
                if (match) sel.value = match;
            });

            if (isEdit && prefill.lat != null && prefill.lng != null) {
                document.getElementById('ftthDevLat').value = prefill.lat;
                document.getElementById('ftthDevLng').value = prefill.lng;
                document.getElementById('ftthDevLocation').value = prefill.location || '';
                ftthPlaceTag([prefill.lat, prefill.lng], true);
                setDeviceStatus('Pin perangkat — geser pin untuk ubah lokasi', '#94a3b8');
            } else {
                ftthPlaceTag(map.getCenter());
            }
            document.getElementById('ftthAddDeviceBackdrop').hidden = false;
            map.on('click', ftthOnMapTagClick);
        }

        function ftthCloseAddDevice() {
            document.getElementById('ftthAddDeviceBackdrop').hidden = true;
            map.off('click', ftthOnMapTagClick);
            if (ftthTagMarker) {
                map.removeLayer(ftthTagMarker);
                ftthTagMarker = null;
            }
        }

        var ftthTagMarker = null;

        function ftthOnMapTagClick(e) {
            ftthPlaceTag(e.latlng);
        }

        function ftthPlaceTag(latlng, noGeo) {
            if (!(latlng instanceof L.LatLng)) latlng = L.latLng(latlng);
            if (!ftthTagMarker) {
                ftthTagMarker = L.marker(latlng, {
                    draggable: true,
                    icon: L.divIcon({
                        className: 'ftth-tag-icon',
                        html: '<i class="fa-solid fa-location-dot"></i>',
                        iconSize: [30, 30],
                        iconAnchor: [15, 27]
                    })
                }).addTo(map);
                ftthTagMarker.on('drag', function() { ftthTagSync(ftthTagMarker.getLatLng()); });
                ftthTagMarker.on('dragend', function() { ftthTagSync(ftthTagMarker.getLatLng()); ftthReverseGeocode(ftthTagMarker.getLatLng()); });
            } else {
                ftthTagMarker.setLatLng(latlng);
            }
            ftthTagSync(latlng);
            if (!noGeo) ftthReverseGeocode(latlng);
        }

        function ftthTagSync(latlng) {
            document.getElementById('ftthDevLat').value = latlng.lat.toFixed(6);
            document.getElementById('ftthDevLng').value = latlng.lng.toFixed(6);
        }

        var ftthGeoSeq = 0;

        function ftthReverseGeocode(latlng) {
            var seq = ++ftthGeoSeq;
            var loc = document.getElementById('ftthDevLocation');
            if (!loc) return;
            fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + latlng.lat + '&lon=' + latlng.lng)
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (seq !== ftthGeoSeq) return;
                    if (d && d.display_name) {
                        loc.value = d.display_name.split(',').slice(0, 3).join(',').trim();
                        
                    }
                })
                .catch(function() {});
        }

        function setDeviceStatus(text, color) {
            var el = document.getElementById('ftthDeviceStatus');
            if (el) {
                el.textContent = text;
                el.style.color = color || '#94a3b8';
            }
        }

        function ftthSaveDevice() {
            var type = document.getElementById('ftthDeviceType').value;
            var name = document.getElementById('ftthDevName').value.trim();
            if (!type) {
                setDeviceStatus('Pilih type perangkat terlebih dahulu', '#f87171');
                return;
            }
            if (!name) {
                setDeviceStatus('Nama perangkat wajib diisi', '#f87171');
                return;
            }

            var attributes = {};
            var parent = document.getElementById('ftthDevParent').value;
            if (parent) attributes.induk = parent;
            attributes.management_core = document.getElementById('ftthDevCoreMgmt').checked ? 1 : 0;

            var capacity = null;
            var ip = null;
            if (type === 'odc' || type === 'odp') {
                var cap = document.getElementById('ftthDevCapacity');
                capacity = (cap && cap.value.trim()) ? cap.value.trim() : null;
            } else if (type === 'onu') {
                var ipEl = document.getElementById('ftthDevIp');
                ip = (ipEl && ipEl.value.trim()) ? ipEl.value.trim() : null;
                var ppEl = document.getElementById('ftthDevPppoe');
                if (ppEl && ppEl.value.trim()) attributes.pppoe_user = ppEl.value.trim();
            }

            if (type === 'olt' && document.getElementById('ftthDevCoreMgmt').checked) {
                var ponCount = document.getElementById('ftthDevPonCount');
                if (ponCount && ponCount.value.trim()) attributes.jumlah_pon = ponCount.value.trim();
            }

            if ((type === 'odc' || type === 'odp') && document.getElementById('ftthDevCoreMgmt').checked) {
                var ponNo = document.getElementById('ftthDevPonNo');
                var coreCol = document.getElementById('ftthDevCoreColor');
                if (ponNo && ponNo.value) attributes.nomor_pon = ponNo.value;
                if (coreCol && coreCol.value) attributes.warna_core = coreCol.value;
            }

            var payload = {
                id: ftthEditDeviceId,
                type: type,
                name: name,
                capacity: capacity,
                ip_address: ip,
                notes: document.getElementById('ftthDevNotes').value.trim(),
                latitude: document.getElementById('ftthDevLat').value.trim(),
                longitude: document.getElementById('ftthDevLng').value.trim(),
                location: document.getElementById('ftthDevLocation').value.trim(),
                attributes: attributes
            };

            setDeviceStatus('Menyimpan...', '#60a5fa');
            mtApi('/noc/features/map/device/save', 'POST', payload).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthToast(r.data.message || 'Perangkat disimpan', 'ok');
                    setDeviceStatus('Tersimpan', '#4ade80');
                    ftthEditDeviceId = null;
                    ftthParentsLoaded = false;
                    loadMapMarkers();
                    setTimeout(ftthCloseAddDevice, 900);
                } else {
                    setDeviceStatus((r.data && r.data.error) || 'Gagal simpan', '#f87171');
                }
            }).catch(function() {
                setDeviceStatus('Gagal menyimpan', '#f87171');
            });
        }

        function ftthOpenDevices() {
            document.getElementById('ftthDevicesBackdrop').hidden = false;
            document.getElementById('ftthDevicesList').innerHTML = '<div class="ftth-device-empty">Memuat data perangkat...</div>';
            document.getElementById('ftthDevicesCount').textContent = '';
            loadDevices();
        }

        function ftthCloseDevices() {
            document.getElementById('ftthDevicesBackdrop').hidden = true;
        }

        function loadDevices() {
            mtApi('/noc/features/map/device', 'GET').then(function(r) {
                if (r.data && r.data.ok && r.data.devices) {
                    renderDevices(r.data.devices);
                } else {
                    document.getElementById('ftthDevicesList').innerHTML = '<div class="ftth-device-empty">Tidak ada data.</div>';
                }
            }).catch(function() {
                document.getElementById('ftthDevicesList').innerHTML = '<div class="ftth-device-empty">Gagal memuat data.</div>';
            });
        }

        function renderDevices(list) {
            var box = document.getElementById('ftthDevicesList');
            document.getElementById('ftthDevicesCount').textContent = list.length + ' perangkat';
            if (!list.length) {
                box.innerHTML = '<div class="ftth-device-empty">Belum ada perangkat. Klik tombol + untuk menambah.</div>';
                return;
            }
            box.innerHTML = list.map(function(d) {
                var color = ftthDeviceColor(d.type);
                var sub = [d.brand, d.model, d.serial_number, d.ip_address, d.capacity].filter(Boolean).join(' · ');
                var loc = d.location || '';
                var stClass = d.status === 'online' ? 'st-online' : (d.status === 'offline' ? 'st-offline' : '');
                var stIcon = d.status === 'online' ? 'fa-wifi' : (d.status === 'offline' ? 'fa-circle-xmark' : 'fa-circle-question');
                var stLabel = d.status ? d.status.toUpperCase() : 'SET';
                return '<div class="ftth-device-row">' +
                    '<span class="ftth-device-type-badge" style="background:' + color + '">' + escapeHtml(d.type_label) + '</span>' +
                    '<span class="ftth-device-row-main">' +
                    '<span class="ftth-device-row-name">' + escapeHtml(d.name) + '</span>' +
                    '<span class="ftth-device-row-sub">' + escapeHtml(sub) + (loc ? ' — ' + escapeHtml(loc) : '') +
                    (d.latitude != null ? ' (' + d.latitude + ', ' + d.longitude + ')' : '') + '</span>' +
                    '</span>' +
                    '<button type="button" class="ftth-device-row-status ' + stClass + '" title="Klik untuk ganti status" onclick="ftthToggleStatus(' + d.id + ', \'' + (d.status || '') + '\')"><i class="fa-solid ' + stIcon + '"></i> ' + stLabel + '</button>' +
                    '<button type="button" class="ftth-device-row-del" title="Hapus" onclick="ftthDeleteDevice(' + d.id + ')"><i class="fa-solid fa-trash-can"></i></button>' +
                    '</div>';
            }).join('');
        }

        function ftthToggleStatus(id, cur) {
            var next = cur === 'online' ? 'offline' : 'online';
            mtApi('/noc/features/map/device/status', 'POST', { id: id, status: next }).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthToast(r.data.message || 'Status diperbarui', 'ok');
                    loadDevices();
                    loadMapMarkers();
                } else {
                    ftthToast((r.data && r.data.error) || 'Gagal mengubah status', 'error');
                }
            }).catch(function() {
                ftthToast('Gagal mengubah status', 'error');
            });
        }

        function ftthDeleteDevice(id) {
            if (!confirm('Hapus perangkat ini?')) return;
            mtApi('/noc/features/map/device/delete', 'POST', { id: id }).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthToast(r.data.message || 'Perangkat dihapus', 'ok');
                    ftthParentsLoaded = false;
                    loadDevices();
                    loadMapMarkers();
                }
            }).catch(function() {
                ftthToast('Gagal menghapus perangkat', 'error');
            });
        }

        function ftthOpenOnuTable() {
            document.getElementById('ftthOnuTableBackdrop').hidden = false;
            document.getElementById('ftthOnuTableBody').innerHTML = '<tr><td colspan="6" class="ftth-device-empty">Memuat data ONU...</td></tr>';
            document.getElementById('ftthOnuTableCount').textContent = '';
            loadOnuTable();
        }

        function ftthCloseOnuTable() {
            document.getElementById('ftthOnuTableBackdrop').hidden = true;
        }

        function loadOnuTable() {
            mtApi('/noc/features/map/device', 'GET').then(function(r) {
                if (r.data && r.data.ok) {
                    renderOnuTable((r.data.devices || []).filter(function(d) { return d.type === 'onu'; }));
                }
            }).catch(function() {
                document.getElementById('ftthOnuTableBody').innerHTML = '<tr><td colspan="6" class="ftth-device-empty">Gagal memuat data.</td></tr>';
            });
        }

        function renderOnuTable(list) {
            var body = document.getElementById('ftthOnuTableBody');
            document.getElementById('ftthOnuTableCount').textContent = list.length + ' ONU';
            if (!list.length) {
                body.innerHTML = '<tr><td colspan="6" class="ftth-device-empty">Belum ada perangkat ONU.</td></tr>';
                return;
            }
            body.innerHTML = list.map(function(d, i) {
                return '<tr>' +
                    '<td>' + (i + 1) + '</td>' +
                    '<td><b>' + escapeHtml(d.name) + '</b>' + (d.serial_number ? '<br><small>' + escapeHtml(d.serial_number) + '</small>' : '') + '</td>' +
                    '<td>' + escapeHtml(d.mac_address || '-') + '</td>' +
                    '<td>' + escapeHtml(d.brand || '-') + '</td>' +
                    '<td>' + escapeHtml(d.model || '-') + '</td>' +
                    '<td>' + escapeHtml(d.location || '-') + '</td>' +
                    '</tr>';
            }).join('');
        }

        /* ── Card info perangkat (klik marker) ── */

        var ftthDetailData = null;
        var ftthCardGeoSeq = 0;

        function ftthCardGeocode(lat, lng) {
            var seq = ++ftthCardGeoSeq;
            fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + lat + '&lon=' + lng)
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (seq !== ftthCardGeoSeq) return;
                    if (d && d.display_name) {
                        var short = d.display_name.split(',').slice(0, 3).join(',').trim();
                        document.getElementById('ftthDetailLoc').innerHTML = '<i class="fa-solid fa-location-dot"></i><span>' + escapeHtml(short) + '</span>';
                    } else {
                        document.getElementById('ftthDetailLoc').innerHTML = '<i class="fa-solid fa-location-dot"></i><span>Tidak diketahui</span>';
                    }
                })
                .catch(function() {
                    if (seq !== ftthCardGeoSeq) return;
                    document.getElementById('ftthDetailLoc').innerHTML = '<i class="fa-solid fa-location-dot"></i><span>Tidak diketahui</span>';
                });
        }

        function ftthShowDetail(m) {
            ftthDetailData = m;
            var color = ftthDeviceColor(m.type);
            document.getElementById('ftthDetailBadge').textContent = String(m.type).toUpperCase();
            document.getElementById('ftthDetailBadge').style.background = color;
            document.getElementById('ftthDetailName').textContent = m.label;

            var stEl = document.getElementById('ftthDetailStatus');
            if (m.status === 'online') {
                stEl.className = 'ftth-device-row-status st-online';
                stEl.innerHTML = '<i class="fa-solid fa-wifi"></i> ONLINE';
            } else if (m.status === 'offline') {
                stEl.className = 'ftth-device-row-status st-offline';
                stEl.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> OFFLINE';
            } else {
                stEl.className = 'ftth-device-row-status';
                stEl.innerHTML = '<i class="fa-solid fa-circle-question"></i> SET';
            }

            var locEl = document.getElementById('ftthDetailLoc');
            if (m.location) {
                locEl.innerHTML = '<i class="fa-solid fa-location-dot"></i><span>' + escapeHtml(m.location) + '</span>';
            } else {
                locEl.innerHTML = '<i class="fa-solid fa-location-dot"></i><span>Mencari alamat...</span>';
                ftthCardGeocode(m.lat, m.lon);
            }
            document.getElementById('ftthDetailCoords').innerHTML =
                '<i class="fa-solid fa-map-pin"></i><span>' + m.lat + ', ' + m.lon + '</span>';

            var rows = [];
            if (m.parent) rows.push(['Induk', m.parent]);
            if (m.detail) rows.push(['Detail', m.detail]);
            if (m.brand) rows.push(['Brand', m.brand]);
            if (m.model) rows.push(['Model', m.model]);
            if (m.capacity) rows.push(['Kapasitas', m.capacity]);
            if (m.ip_address) rows.push(['IP', m.ip_address]);
            var attrs = m.attributes || {};
            if (typeof attrs === 'object') {
                Object.keys(attrs).forEach(function(k) {
                    var v = attrs[k];
                    if (v === null || v === undefined || v === '') return;
                    if (k === 'management_core') {
                        if (Number(v) === 1) rows.push(['Management Core', 'Ya']);
                        return;
                    }
                    var label = String(k).replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
                    rows.push([label, v]);
                });
            }
            document.getElementById('ftthDetailAttrs').innerHTML = rows.map(function(r) {
                return '<div class="ftth-detail-attr"><b>' + escapeHtml(r[0]) + '</b><span>' + escapeHtml(String(r[1])) + '</span></div>';
            }).join('');

            var notesEl = document.getElementById('ftthDetailNotes');
            if (m.notes) {
                notesEl.hidden = false;
                notesEl.innerHTML = '<i class="fa-solid fa-note-sticky"></i> ' + escapeHtml(m.notes);
            } else {
                notesEl.hidden = true;
            }

            var actions = document.getElementById('ftthDetailActions');
            actions.innerHTML = '';
            if (m.source === 'device') {
                actions.innerHTML =
                    '<button type="button" class="ftth-modal-btn save" onclick="ftthEditFromDetail()"><i class="fa-solid fa-pen"></i> Edit</button>' +
                    '<button type="button" class="ftth-modal-btn" onclick="ftthDetailToggleStatus()"><i class="fa-solid fa-power-off"></i> Status</button>' +
                    '<button type="button" class="ftth-detail-del" onclick="ftthDetailDelete()" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>';
            }

            document.getElementById('ftthDetailCard').hidden = false;
        }

        function ftthCloseDetail() {
            var el = document.getElementById('ftthDetailCard');
            if (el) el.hidden = true;
            ftthDetailData = null;
        }

        function ftthEditFromDetail() {
            var m = ftthDetailData;
            if (!m || m.source !== 'device') return;
            var attrs = m.attributes || {};
            ftthOpenAddDevice({
                id: m.id,
                type: String(m.type).toLowerCase(),
                name: m.label,
                notes: m.notes || '',
                parent: attrs.induk || '',
                capacity: m.capacity,
                ip: m.ip_address,
                pppoe: attrs.pppoe_user,
                management_core: Number(attrs.management_core) === 1,
                jumlah_pon: attrs.jumlah_pon,
                nomor_pon: attrs.nomor_pon,
                warna_core: attrs.warna_core,
                lat: m.lat,
                lng: m.lon,
                location: m.location || ''
            });
        }

        function ftthDetailToggleStatus() {
            var m = ftthDetailData;
            if (!m || m.source !== 'device') return;
            var next = m.status === 'online' ? 'offline' : 'online';
            mtApi('/noc/features/map/device/status', 'POST', { id: m.id, status: next }).then(function(r) {
                if (r.data && r.data.ok) {
                    m.status = next;
                    ftthShowDetail(m);
                    loadMapMarkers();
                    ftthToast(r.data.message || 'Status diperbarui', 'ok');
                } else {
                    ftthToast((r.data && r.data.error) || 'Gagal mengubah status', 'error');
                }
            }).catch(function() {
                ftthToast('Gagal mengubah status', 'error');
            });
        }

        function ftthDetailDelete() {
            var m = ftthDetailData;
            if (!m || m.source !== 'device') return;
            if (!confirm('Hapus perangkat "' + m.label + '"?')) return;
            mtApi('/noc/features/map/device/delete', 'POST', { id: m.id }).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthCloseDetail();
                    loadMapMarkers();
                    ftthToast(r.data.message || 'Perangkat dihapus', 'ok');
                } else {
                    ftthToast((r.data && r.data.error) || 'Gagal menghapus perangkat', 'error');
                }
            }).catch(function() {
                ftthToast('Gagal menghapus perangkat', 'error');
            });
        }

        /* ── Card ONU pelanggan (klik marker pelanggan) ── */

        var ftthCustDetail = null;
        var ftthAcsInfo = null;
        var ftthCustBusy = false;

        function ftthLogRow(cls, text) {
            var el = document.getElementById('ftthDetailLog');
            el.hidden = false;
            var row = document.createElement('div');
            row.className = 'ftth-log-row ' + (cls || 'info');
            row.textContent = text;
            el.appendChild(row);
            el.scrollTop = el.scrollHeight;
        }

        function ftthClearLog() {
            var el = document.getElementById('ftthDetailLog');
            if (el) {
                el.innerHTML = '';
                el.hidden = true;
            }
        }

        function ftthCopyText(text, label) {
            var t = (text === null || text === undefined || text === '') ? '-' : String(text);
            var ok = function() { ftthToast((label || 'Nilai') + ' disalin', 'ok'); };
            if (!navigator.clipboard) {
                var ta = document.createElement('textarea');
                ta.value = t;
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (e) {}
                document.body.removeChild(ta);
                ok();
                return;
            }
            navigator.clipboard.writeText(t).then(ok).catch(function() {
                ftthToast('Gagal menyalin', 'error');
            });
        }

        function ftthAttrRow(label, value, copy) {
            if (value === null || value === undefined || value === '') value = '-';
            var btn = '';
            if (copy) {
                btn = '<button type="button" class="ftth-copy-btn" title="Salin ' + label + '" onclick="ftthCopyText(this.dataset.v,\'' + label + '\')" data-v="' + escapeHtml(String(value)) + '"><i class="fa-solid fa-copy"></i></button>';
            }
            return '<div class="ftth-detail-attr' + (copy ? ' has-copy' : '') + '"><b>' + label + '</b><span>' + escapeHtml(String(value)) + '</span>' + btn + '</div>';
        }

        function ftthStatusLabel(status) {
            var s = String(status || '').toLowerCase();
            if (s === 'online') return { cls: 'st-online', html: '<i class="fa-solid fa-wifi"></i> ONLINE' };
            if (s === 'offline') return { cls: 'st-offline', html: '<i class="fa-solid fa-circle-xmark"></i> OFFLINE' };
            if (!s || s === 'null' || s === 'undefined') return { cls: '', html: '<i class="fa-solid fa-circle-question"></i> -' };
            return { cls: 'st-other', html: '<i class="fa-solid fa-triangle-exclamation"></i> ' + escapeHtml(s.toUpperCase()) };
        }

        function ftthHumanBytes(b) {
            if (b === null || b === undefined) return '-';
            var n = Number(b);
            if (!isFinite(n) || n < 0) return '-';
            var units = ['B', 'KB', 'MB', 'GB', 'TB'];
            var i = 0;
            while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
            return n.toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
        }

        function ftthCustRenderActions(m, d) {
            d = d || ftthCustDetail || {};
            var busyAttr = ftthCustBusy ? ' disabled' : '';
            var acts =
                '<button type="button" class="ftth-cust-btn"' + busyAttr + ' onclick="ftthCustPing()"><i class="fa-solid fa-network-wired"></i> PING</button>' +
                '<button type="button" class="ftth-cust-btn"' + busyAttr + ' onclick="ftthCustDetailRefresh()" title="Muat ulang status"><i class="fa-solid fa-rotate"></i> Status</button>' +
                '<button type="button" class="ftth-cust-btn"' + busyAttr + ' onclick="ftthCustAcs()" title="Info perangkat ACS"><i class="fa-solid fa-server"></i> ACS</button>' +
                '<button type="button" class="ftth-cust-btn"' + busyAttr + ' onclick="ftthCustWifi()" title="Ambil SSID / WiFi"><i class="fa-solid fa-wifi"></i> Wifi</button>' +
                '<button type="button" class="ftth-cust-btn danger"' + busyAttr + ' onclick="ftthCustReboot()" title="Reboot ONU"><i class="fa-solid fa-power-off"></i> Reboot</button>' +
                '<button type="button" class="ftth-cust-btn"' + busyAttr + ' onclick="ftthCustSalin()" title="Salin ringkasan"><i class="fa-solid fa-copy"></i> Salin</button>';
            if (d.maps) acts += '<a class="ftth-cust-btn" target="_blank" rel="noopener" href="' + escapeHtml(d.maps) + '" title="Buka di Google Maps"><i class="fa-solid fa-map-location-dot"></i> Maps</a>';
            if (d.wa) acts += '<a class="ftth-cust-btn whatsapp" target="_blank" rel="noopener" href="' + escapeHtml(d.wa) + '" title="Chat WhatsApp"><i class="fa-brands fa-whatsapp"></i> WA</a>';
            if (d.edit) acts += '<a class="ftth-cust-btn" target="_blank" rel="noopener" href="' + escapeHtml(d.edit) + '" title="Edit pelanggan"><i class="fa-solid fa-pen"></i> Edit</a>';
            acts += '<button type="button" class="ftth-cust-btn"' + busyAttr + ' onclick="ftthCustDuplicate()" title="Duplikat pelanggan"><i class="fa-solid fa-clone"></i> Duplikat</button>';
            document.getElementById('ftthDetailActions').innerHTML = acts;
        }

        function ftthShowCustomer(m) {
            ftthDetailData = m;
            ftthCustDetail = null;
            ftthAcsInfo = null;
            ftthCustBusy = false;
            ftthClearLog();

            document.getElementById('ftthDetailBadge').textContent = 'PELANGGAN';
            document.getElementById('ftthDetailBadge').style.background = '#22c55e';
            document.getElementById('ftthDetailName').textContent = m.label;

            var stEl = document.getElementById('ftthDetailStatus');
            if (m.onu_status) {
                var st = ftthStatusLabel(m.onu_status);
                stEl.className = 'ftth-device-row-status ' + st.cls;
                stEl.innerHTML = st.html;
            } else {
                stEl.className = 'ftth-device-row-status st-other';
                stEl.innerHTML = '<i class="fa-solid fa-spinner ftth-spin"></i> MEMUAT…';
            }

            var locEl = document.getElementById('ftthDetailLoc');
            if (m.location) {
                locEl.innerHTML = '<i class="fa-solid fa-location-dot"></i><span>' + escapeHtml(m.location) + '</span>';
            } else {
                locEl.innerHTML = '<i class="fa-solid fa-location-dot"></i><span>Mencari alamat...</span>';
                ftthCardGeocode(m.lat, m.lon);
            }
            document.getElementById('ftthDetailCoords').innerHTML =
                '<i class="fa-solid fa-map-pin"></i><span>' + m.lat + ', ' + m.lon + '</span>';

            var rows = [];
            if (m.parent) rows.push(['Induk', m.parent]);
            if (m.detail) rows.push(['Tipe', m.detail]);
            rows.push(['Status', m.billing === 'active' ? 'Aktif' : (m.billing === 'suspended' ? 'Ditangguhkan' : (m.billing || '-'))]);
            document.getElementById('ftthDetailAttrs').innerHTML = rows.map(function(r) {
                return '<div class="ftth-detail-attr"><b>' + r[0] + '</b><span>' + escapeHtml(String(r[1])) + '</span></div>';
            }).join('');

            document.getElementById('ftthDetailNotes').hidden = true;
            document.getElementById('ftthDetailLive').hidden = true;

            ftthCustRenderActions(m, null);

            document.getElementById('ftthDetailCard').hidden = false;

            ftthCustLoad();
        }

        function ftthCustLoad() {
            var m = ftthDetailData;
            if (!m || m.source !== 'customer') return;
            ftthCustBusy = true;
            ftthCustRenderActions(m, ftthCustDetail);
            mtApi('/noc/features/map/customer/detail?id=' + encodeURIComponent(m.id), 'GET').then(function(r) {
                ftthCustBusy = false;
                var d = (r.data && r.data.ok) ? r.data : null;
                if (!d) {
                    ftthLogRow('err', (r.data && r.data.error) || 'Gagal memuat detail pelanggan');
                    ftthCustRenderActions(m, null);
                    return;
                }
                ftthCustDetail = d;
                ftthCustRender(m, d);
            }).catch(function() {
                ftthCustBusy = false;
                ftthLogRow('err', 'Gagal memuat detail pelanggan (jaringan)');
                ftthCustRenderActions(m, null);
            });
        }

        function ftthCustRender(m, d) {
            var c = d.customer || {};
            var onu = d.onu || {};
            var sess = d.session || {};

            var stEl = document.getElementById('ftthDetailStatus');
            var status = onu.status || m.onu_status;
            if (status) {
                var st = ftthStatusLabel(status);
                stEl.className = 'ftth-device-row-status ' + st.cls;
                stEl.innerHTML = st.html;
            } else {
                stEl.className = 'ftth-device-row-status';
                stEl.innerHTML = '<i class="fa-solid fa-circle-question"></i> -';
            }

            var rows = [];
            if (c.package) rows.push(['Paket', c.package]);
            if (c.odp) rows.push(['ODP', c.odp]);
            if (c.odp_port) rows.push(['Port ODP', c.odp_port]);
            rows.push(['Status', c.status === 'active' ? 'Aktif' : (c.status === 'suspended' ? 'Ditangguhkan' : (c.status || '-'))]);
            if (c.due_date) rows.push(['Tenggat', c.due_date]);
            if (c.pppoe_username) rows.push(['PPPoE', c.pppoe_username]);
            if (c.serial_number) rows.push(['Serial', c.serial_number]);
            if (c.mac_address) rows.push(['MAC', c.mac_address]);
            if (m.parent) rows.push(['Induk', m.parent]);
            document.getElementById('ftthDetailAttrs').innerHTML = rows.map(function(r) {
                return '<div class="ftth-detail-attr"><b>' + r[0] + '</b><span>' + escapeHtml(String(r[1])) + '</span></div>';
            }).join('');

            var live = [];
            live.push('<div class="ftth-detail-live-head"><span>ONU & Sesi</span></div>');
            live.push(ftthAttrRow('IP', sess.ip || onu.acs_ip || '-', true));
            var portOlt = (onu.slot || onu.port) ? ((onu.slot || '-') + '/' + (onu.port || '-')) : '-';
            live.push(ftthAttrRow('Port OLT', portOlt, true));
            live.push(ftthAttrRow('ONU ID', onu.onu_id || onu.serial_number || '-', true));
            var rx = (onu.rx_power !== null && onu.rx_power !== undefined) ? onu.rx_power + ' dBm' : '-';
            var tx = (onu.tx_power !== null && onu.tx_power !== undefined) ? onu.tx_power + ' dBm' : '-';
            live.push(ftthAttrRow('Rx / Tx', rx + ' / ' + tx, false));
            live.push(ftthAttrRow('Uptime', sess.uptime || (onu.uptime ? onu.uptime + ' s' : '-'), false));
            live.push(ftthAttrRow('Traffic ↓', ftthHumanBytes(sess.bytes_in), false));
            live.push(ftthAttrRow('Traffic ↑', ftthHumanBytes(sess.bytes_out), false));
            if (sess.router_name) live.push(ftthAttrRow('Router', sess.router_name, false));
            var acsTxt = onu.acs_status ? String(onu.acs_status).toUpperCase() : '-';
            if (onu.acs_device_id) acsTxt += ' • ' + onu.acs_device_id;
            live.push(ftthAttrRow('ACS', acsTxt, false));
            if (ftthAcsInfo) {
                if (ftthAcsInfo.ssid) live.push(ftthAttrRow('SSID', ftthAcsInfo.ssid, true));
                var wifiOn = String(ftthAcsInfo.wifi_enabled) === '1' || ftthAcsInfo.wifi_enabled === true || String(ftthAcsInfo.wifi_enabled).toLowerCase() === 'true';
                if (ftthAcsInfo.wifi_enabled !== null && ftthAcsInfo.wifi_enabled !== undefined) live.push(ftthAttrRow('WiFi', wifiOn ? 'Aktif' : 'Mati', false));
                if (ftthAcsInfo.external_ip) live.push(ftthAttrRow('IP Publik', ftthAcsInfo.external_ip, true));
                if (ftthAcsInfo.channel) live.push(ftthAttrRow('Channel', ftthAcsInfo.channel, false));
            }
            var liveEl = document.getElementById('ftthDetailLive');
            liveEl.innerHTML = live.join('');
            liveEl.hidden = false;

            ftthCustRenderActions(m, d);
        }

        function ftthCustDetailRefresh() {
            var m = ftthDetailData;
            if (!m || m.source !== 'customer') return;
            ftthClearLog();
            ftthCustLoad();
        }

        function ftthCustPing() {
            var m = ftthDetailData;
            if (!m || m.source !== 'customer') return;
            ftthClearLog();
            ftthLogRow('info', 'Ping …');
            mtApi('/noc/features/map/customer/ping', 'POST', { id: m.id }).then(function(r) {
                if (r.data && r.data.ok) {
                    var res = r.data.result || {};
                    var line = 'Host: ' + (r.data.host || '-') + '\n' +
                        'Status: ' + res.status + '\n' +
                        'Latency: ' + (res.latency_ms !== null && res.latency_ms !== undefined ? res.latency_ms + ' ms' : '-') + '\n' +
                        'Jitter: ' + (res.jitter_ms !== null && res.jitter_ms !== undefined ? res.jitter_ms + ' ms' : '-') + '\n' +
                        'Packet loss: ' + res.packet_loss_percent + ' %';
                    ftthLogRow(res.status === 'online' ? 'ok' : (res.status === 'warning' ? 'info' : 'err'), line);
                } else {
                    ftthLogRow('err', (r.data && r.data.error) || 'Gagal ping');
                }
            }).catch(function() {
                ftthLogRow('err', 'Gagal ping (jaringan)');
            });
        }

        function ftthCustAcs() {
            var m = ftthDetailData;
            if (!m || m.source !== 'customer') return;
            var onu = (ftthCustDetail && ftthCustDetail.onu) || {};
            if (!onu.acs_device_id) {
                ftthLogRow('err', 'Belum ada perangkat ACS tersambung untuk pelanggan ini');
                return;
            }
            ftthClearLog();
            ftthLogRow('info', 'Memuat info ACS: ' + onu.acs_device_id + ' …');
            mtApi('/noc/features/map/customer/acs', 'POST', { id: m.id }).then(function(r) {
                if (r.data && r.data.ok) {
                    var a = r.data.acs || {};
                    var line = 'Device: ' + (r.data.device_id || '-') + '\n' +
                        'Pabrikan: ' + (a.manufacturer || '-') + '\n' +
                        'Produk: ' + (a.product_class || '-') + '\n' +
                        'Firmware: ' + (a.software_version || '-') + '\n' +
                        'IP Publik: ' + (a.external_ip || '-') + '\n' +
                        'Mode: ' + (a.mode || '-');
                    ftthLogRow('ok', line);
                } else {
                    ftthLogRow('err', (r.data && r.data.error) || 'Gagal ambil data ACS');
                }
            }).catch(function() {
                ftthLogRow('err', 'Gagal ambil data ACS (jaringan)');
            });
        }

        function ftthCustWifi() {
            var m = ftthDetailData;
            if (!m || m.source !== 'customer') return;
            ftthClearLog();
            ftthLogRow('info', 'Mengambil SSID / WiFi …');
            mtApi('/noc/features/map/customer/acs', 'POST', { id: m.id }).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthAcsInfo = r.data.acs || {};
                    var a = ftthAcsInfo;
                    var wifiOn = String(a.wifi_enabled) === '1' || a.wifi_enabled === true || String(a.wifi_enabled).toLowerCase() === 'true';
                    var line = 'SSID: ' + (a.ssid || '-') + '\n' +
                        'WiFi: ' + (wifiOn ? 'Aktif' : 'Mati') + '\n' +
                        'Channel: ' + (a.channel || '-') + '\n' +
                        'Mode: ' + (a.mode || '-');
                    ftthLogRow('ok', line);
                    ftthCustRender(m, ftthCustDetail);
                } else {
                    ftthLogRow('err', (r.data && r.data.error) || 'Gagal ambil SSID');
                }
            }).catch(function() {
                ftthLogRow('err', 'Gagal ambil SSID (jaringan)');
            });
        }

        function ftthCustReboot() {
            var m = ftthDetailData;
            if (!m || m.source !== 'customer') return;
            var onu = (ftthCustDetail && ftthCustDetail.onu) || {};
            if (!onu.onu_id) {
                ftthLogRow('err', 'Pelanggan tidak memiliki ONU (OLT) untuk direboot');
                return;
            }
            if (!confirm('Reboot ONU ' + (onu.onu_id || '') + '?')) return;
            ftthClearLog();
            ftthLogRow('info', 'Mengirim perintah reboot …');
            mtApi('/noc/features/map/onu/reboot', 'POST', { onu_id: onu.id }).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthLogRow('ok', r.data.message || 'Perintah reboot terkirim');
                } else {
                    ftthLogRow('err', (r.data && r.data.error) || 'Gagal reboot');
                }
            }).catch(function() {
                ftthLogRow('err', 'Gagal reboot (jaringan)');
            });
        }

        function ftthCustDuplicate() {
            var m = ftthDetailData;
            if (!m || m.source !== 'customer') return;
            if (!confirm('Duplikat pelanggan "' + m.label + '"?')) return;
            ftthClearLog();
            ftthLogRow('info', 'Membuat duplikat …');
            mtApi('/noc/features/map/customer/duplicate', 'POST', { id: m.id }).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthLogRow('ok', r.data.message + (r.data.edit ? ' — buka halaman Edit untuk menyesuaikan' : ''));
                } else {
                    ftthLogRow('err', (r.data && r.data.error) || 'Gagal duplikat');
                }
            }).catch(function() {
                ftthLogRow('err', 'Gagal duplikat (jaringan)');
            });
        }

        function ftthCustSalin() {
            var m = ftthDetailData;
            if (!m || m.source !== 'customer') return;
            var d = ftthCustDetail || {};
            var c = d.customer || {};
            var onu = d.onu || {};
            var sess = d.session || {};
            var billingTxt = c.status === 'active' ? 'Aktif' : (c.status === 'suspended' ? 'Ditangguhkan' : (c.status || '-'));
            var portOlt = (onu.slot || onu.port) ? ((onu.slot || '-') + '/' + (onu.port || '-')) : '-';
            var lines = [
                m.label,
                'Status: ' + billingTxt + ' • ONU: ' + (onu.status ? String(onu.status).toUpperCase() : '-'),
                'IP: ' + (sess.ip || onu.acs_ip || '-'),
                'Port OLT: ' + portOlt + ' • Port ODP: ' + (c.odp_port || '-'),
                'Paket: ' + (c.package || '-'),
                'Lokasi: ' + (c.location || m.location || '-'),
                'Koordinat: ' + m.lat + ', ' + m.lon,
                d.maps ? 'Maps: ' + d.maps : '',
                d.wa ? 'WA: ' + d.wa : '',
                d.edit ? 'Edit: ' + d.edit : ''
            ].filter(Boolean).join('\n');
            ftthCopyText(lines, 'Ringkasan');
        }

        /* ── Marker peta: OLT / Router / ODC / Perangkat / Customer ── */

        var markerLayer = null;
        var cableLayer = null;
        var markersCache = [];

        var deviceIcons = {
            'OLT': 'fa-server',
            'ODC': 'fa-cabinet-filing',
            'OTB': 'fa-box-archive',
            'ODP': 'fa-code-branch',
            'HTB': 'fa-diagram-project',
            'CLOSURE': 'fa-link',
            'ONU': 'fa-wifi',
            'CUSTOMER': 'fa-wifi',
            'CUSTOM': 'fa-microchip',
            'ROUTER': 'fa-tower-cell'
        };

        function ftthDeviceIcon(type, status, color) {
            var t = String(type).toUpperCase();
            var icon = deviceIcons[t] || 'fa-microchip';
            var blink = (status === 'online') ? ' blink-on' : (status === 'offline') ? ' blink-off' : '';
            var iconColor = blink ? '' : (color || '');
            var styleAttr = iconColor ? ' style="color:' + iconColor + '"' : '';
            return L.divIcon({
                className: 'ftth-marker-label',
                html: '<div class="ftth-ic' + blink + '">' +
                    '<div class="ftth-ic-i"><i class="fa-solid ' + icon + '"' + styleAttr + '></i></div>' +
                    '</div>',
                iconSize: [26, 26],
                iconAnchor: [13, 13]
            });
        }

        function loadMapMarkers() {
            if (!markerLayer) markerLayer = L.layerGroup().addTo(map);
            if (!cableLayer) cableLayer = L.layerGroup().addTo(map);
            mtApi('/noc/features/map/markers', 'GET').then(function(r) {
                markersCache = (r.data && r.data.ok && r.data.markers) ? r.data.markers : [];
                renderMapMarkers();
            }).catch(function() { markersCache = []; });
        }

        function ftthVisCategory(m) {
            var t = String(m.type).toUpperCase();
            if (m.source === 'olt' || m.source === 'router') return 'router';
            if (m.source === 'odc') return 'odc';
            if (t === 'ODP') return 'odp';
            if (t === 'OTB') return 'otb';
            if (t === 'CLOSURE' || t === 'HTB') return 'closure';
            if (m.source === 'customer') return (m.onu_status === 'online') ? 'onuOnline' : 'onuOffline';
            return 'other';
        }

        function ftthSpotKey(type, label) {
            return (String(type).toUpperCase() + ' — ' + label).replace(/\s+/g, ' ').trim().toUpperCase();
        }

        function ftthSpotFromString(parent) {
            return String(parent).replace(/\s+/g, ' ').trim().toUpperCase();
        }

        function ftthDrawCables(spots, spotMarkers) {
            markersCache.forEach(function(m) {
                if (!VIS.cable || !m.parent) return;
                var parentKey = ftthSpotFromString(m.parent);
                var from = spots[parentKey];
                if (!from) return;
                var to = [m.lat, m.lon];
                if (Math.abs(from[0] - to[0]) < 1e-7 && Math.abs(from[1] - to[1]) < 1e-7) return;
                var attrs = (m.attributes && typeof m.attributes === 'object') ? m.attributes : {};
                var color = attrs.warna_core;
                if (!color) {
                    var pm = spotMarkers[parentKey];
                    var pAttrs = (pm && pm.attributes && typeof pm.attributes === 'object') ? pm.attributes : {};
                    color = pAttrs.warna_core;
                }
                if (!color) color = ftthDeviceColor(m.type);
                var online = (m.status === 'online' || m.onu_status === 'online');
                var cls = online ? 'ftth-cable ftth-cable-flow' : 'ftth-cable ftth-cable-stop';
                L.polyline([from, to], {
                    className: cls,
                    color: color,
                    weight: online ? 2.5 : 2,
                    opacity: online ? 0.9 : 0.55
                }).addTo(cableLayer);
            });
        }

        function renderMapMarkers() {
            if (!markerLayer) markerLayer = L.layerGroup().addTo(map);
            if (!cableLayer) cableLayer = L.layerGroup().addTo(map);
            markerLayer.clearLayers();
            cableLayer.clearLayers();
            if (!markersCache.length) return;

            var spotMarkers = {};
            markersCache.forEach(function(m) {
                spotMarkers[ftthSpotKey(m.type, m.label)] = m;
            });
            var spots = {};
            Object.keys(spotMarkers).forEach(function(k) {
                var sm = spotMarkers[k];
                spots[k] = [sm.lat, sm.lon];
            });

            ftthDrawCables(spots, spotMarkers);

            markersCache.forEach(function(m) {
                var cat = ftthVisCategory(m);
                if (cat !== 'other' && !VIS[cat]) return;

                var color = ftthDeviceColor(m.type);
                var stTxt = '';
                if (m.status === 'online') stTxt = '<br><span style="color:#4ade80;font-weight:700">● Online</span>';
                else if (m.status === 'offline') stTxt = '<br><span style="color:#f87171;font-weight:700">● Offline</span>';
                var pop = '<b style="color:' + color + '">' + escapeHtml(m.type) + '</b><br>' +
                    '<b>' + escapeHtml(m.label) + '</b>' +
                    stTxt +
                    (m.location ? '<br><span style="color:#60a5fa">' + escapeHtml(m.location) + '</span>' : '') +
                    (m.detail ? '<br><small>' + escapeHtml(m.detail) + '</small>' : '') +
                    '<br><small>' + m.lat + ', ' + m.lon + '</small>';
                var netTypes = ['OLT', 'ROUTER', 'ODC', 'ODP', 'HTB', 'CLOSURE', 'OTB', 'CUSTOM', 'ONU', 'CUSTOMER'];
                var isNet = netTypes.indexOf(m.type.toUpperCase()) !== -1;
                var mk;
                var onuText = '';
                if (m.source === 'customer') {
                    if (VIS.onuText === 'nama') onuText = m.name || '';
                    else if (VIS.onuText === 'pppoe') onuText = m.pppoe_username || '';
                }
                if (isNet) {
                    mk = L.marker([m.lat, m.lon], { icon: ftthDeviceIcon(m.type, m.status, color) });
                    if (onuText) mk.bindTooltip(onuText, {
                        permanent: true,
                        className: 'ftth-onu-label',
                        direction: 'top',
                        offset: [0, -14]
                    });
                } else {
                    mk = L.circleMarker([m.lat, m.lon], {
                        radius: 5,
                        color: '#0b1524',
                        weight: 1.2,
                        fillColor: color,
                        fillOpacity: 0.85
                    });
                }
                mk.addTo(markerLayer);
                mk.on('click', function() {
                    if (m.source === 'customer') ftthShowCustomer(m);
                    else ftthShowDetail(m);
                });
            });
        }

        loadMapMarkers();

        window.ftthOpenAddDevice = function() { ftthOpenAddDevice(); };
        window.ftthCloseAddDevice = function() { ftthCloseAddDevice(); };
        window.ftthRenderDeviceFields = function() { ftthRenderDeviceFields(); };
        window.ftthSaveDevice = function() { ftthSaveDevice(); };
        window.ftthOpenDevices = function() { ftthOpenDevices(); };
        window.ftthCloseDevices = function() { ftthCloseDevices(); };
        window.ftthDeleteDevice = function(id) { ftthDeleteDevice(id); };
        window.ftthToggleStatus = function(id, cur) { ftthToggleStatus(id, cur); };
        window.ftthOpenOnuTable = function() { ftthOpenOnuTable(); };
        window.ftthCloseOnuTable = function() { ftthCloseOnuTable(); };
        window.ftthCloseDetail = function() { ftthCloseDetail(); };
        window.ftthEditFromDetail = function() { ftthEditFromDetail(); };
        window.ftthDetailToggleStatus = function() { ftthDetailToggleStatus(); };
        window.ftthDetailDelete = function() { ftthDetailDelete(); };
        window.ftthCopyText = function(t, l) { ftthCopyText(t, l); };
        window.ftthCustPing = function() { ftthCustPing(); };
        window.ftthCustDetailRefresh = function() { ftthCustDetailRefresh(); };
        window.ftthCustAcs = function() { ftthCustAcs(); };
        window.ftthCustWifi = function() { ftthCustWifi(); };
        window.ftthCustReboot = function() { ftthCustReboot(); };
        window.ftthCustDuplicate = function() { ftthCustDuplicate(); };
        window.ftthCustSalin = function() { ftthCustSalin(); };

        /* ── Auto-sync saat panel dibuka (Mikrotik + OLT + GenieACS) ── */

        var autoSyncBusy = false;

        function setToolbarSyncing(feature, syncing) {
            var btn = document.querySelector('.ftth-btn[data-feature="' + feature + '"]');
            if (!btn) return;
            if (syncing && !btn.dataset.titleOrig) btn.dataset.titleOrig = btn.getAttribute('title') || '';
            btn.classList.toggle('ftth-syncing', syncing);
            btn.setAttribute('title', syncing ? 'Menyinkronkan...' : (btn.dataset.titleOrig || ''));
        }

        function ftthAutoSync() {
            if (autoSyncBusy) return;
            autoSyncBusy = true;
            setToolbarSyncing('sync-mikrotik', true);
            setToolbarSyncing('sync-olt', true);
            setToolbarSyncing('sync-genieacs', true);

            var mikrotik = mtApi('/noc/features/map/mikrotik/sync-all', 'POST')
                .then(function(r) {
                    if (r.data && r.data.ok != null) {
                        setPppoeStats(r.data.pppoe_online, r.data.pppoe_offline);
                        return { ok: r.data.ok, failed: r.data.failed, total: r.data.total };
                    }
                    return null;
                })
                .catch(function() { return null; });

            var olt = mtApi('/noc/features/map/olt/sync-all', 'POST')
                .then(function(r) {
                    if (r.data && r.data.ok != null) {
                        if (r.data.onu_online != null) updateOnuStats(r.data.onu_online, r.data.onu_offline);
                        return { ok: r.data.ok, failed: r.data.failed, total: r.data.total };
                    }
                    return null;
                })
                .catch(function() { return null; });

            var genie = mtApi('/noc/features/map/genieacs/sync', 'POST')
                .then(function(r) { return (r.data && r.data.ok) ? r.data : null; })
                .catch(function() { return null; });

            Promise.all([mikrotik, olt, genie]).then(function(results) {
                setToolbarSyncing('sync-mikrotik', false);
                setToolbarSyncing('sync-olt', false);
                setToolbarSyncing('sync-genieacs', false);
                autoSyncBusy = false;

                var parts = [];
                if (results[0]) parts.push('Mikrotik ' + results[0].ok + '/' + results[0].total);
                if (results[1]) parts.push('OLT ' + results[1].ok + '/' + results[1].total);
                if (results[2]) parts.push('GenieACS ' + (results[2].online || 0) + ' online');
                ftthToast('Auto-sync selesai — ' + (parts.length ? parts.join(', ') : 'tidak ada data'), 'ok');
            });
        }

        ftthAutoSync();
    })();
    </script>
</body>
</html>
