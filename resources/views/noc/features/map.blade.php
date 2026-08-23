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
        /* Selama penerbangan zoom (flyTo): pause semua animasi CSS dekoratif agar frame zoom mulus tanpa jank.
           (Meteor SVG disembunyikan terpisah via handler zoomstart.) */
        #ftth-map.ftth-flying * { animation-play-state: paused !important; }
        /* Selama penerbangan sinematik pencarian (ftth-cinematic): hanya citra bumi + tangan penunjuk yang tampak.
           Marker perangkat/kabel/popup disembunyikan (di zoom jauh ikon mengerumuni satu titik & kabel jadi gumpalan).
           Hanya berlaku bila class ini ada — penerbangan biasa (edit kabel) tetap menampilkan semua layer. */
        #ftth-map.ftth-cinematic .leaflet-marker-icon:not(.ftth-search-hand),
        #ftth-map.ftth-cinematic .leaflet-marker-shadow,
        #ftth-map.ftth-cinematic .leaflet-overlay-pane,
        #ftth-map.ftth-cinematic .leaflet-popup {
            visibility: hidden !important;
        }
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
        #ftthLangBtn { margin-left: auto; }
        .ftth-search-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: auto;
            height: 16px;
            padding: 0 4px 0 8px;
            border: none;
            border-left: 1px solid rgba(96,165,250,0.35);
            border-radius: 0;
            background: transparent;
            color: #facc15;
            font-size: 12px;
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
            background: #0f172a;
            border: 1px solid color-mix(in srgb, var(--ftth-accent) 55%, #0f172a);
            color: #fff;
            font-size: 12px;
            backdrop-filter: none;
            box-shadow: 0 6px 20px rgba(0,0,0,0.35);
            transition: all 0.2s;
            cursor: pointer;
            flex-shrink: 0;
        }
        .ftth-icon-btn i {
            color: var(--ftth-accent);
        }
        .ftth-icon-btn:hover {
            background: color-mix(in srgb, var(--ftth-accent) 55%, #0f172a);
            border-color: var(--ftth-accent);
            color: #fff;
        }
        .ftth-icon-btn:hover i {
            color: #fff;
        }
        .ftth-icon-btn.active {
            border-color: var(--ftth-accent);
            background: color-mix(in srgb, var(--ftth-accent) 40%, #0f172a);
            color: #fff;
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--ftth-accent) 35%, #0f172a), 0 6px 20px rgba(0,0,0,0.35);
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
        .ftth-ac-lock { --ftth-accent: #f87171; }
        .ftth-ac-theme { --ftth-accent: #f59e0b; }
        .ftth-ac-fullscreen { --ftth-accent: #c084fc; }
        .ftth-ac-measure { --ftth-accent: #4ade80; }
        .ftth-ac-back { --ftth-accent: #60a5fa; }
        .ftth-ac-search { --ftth-accent: #facc15; }
        .ftth-ac-lang { --ftth-accent: #38bdf8; }
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
        .ftth-fab span { display: none; }
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
            background: #64748b; color: #fff; cursor: pointer; font-size: 11px;
        }
        .ftth-measure-x:hover { background: #475569; color: #fff; }
        .ftth-measure-result-body { display: flex; flex-direction: column; gap: 4px; font-size: 11px; }
        .fm-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .fm-row b { font-size: 12px; color: #4ade80; }
        .ftth-measure-otdr .fm-row b { color: #38bdf8; }
        .ftth-measure-result-hint { font-size: 9.5px; color: rgba(255,255,255,0.55); margin-top: 8px; line-height: 1.4; }
        .ftth-measure-result-actions { display: flex; gap: 6px; margin-top: 10px; }
        .ftth-measure-act {
            flex: 1;
            display: inline-flex; align-items: center; justify-content: center; gap: 5px;
            padding: 6px 8px; border-radius: 8px; border: 1px solid #475569;
            background: #64748b; color: #fff; font-size: 10px; font-weight: 600; cursor: pointer;
        }
        .ftth-measure-act:hover { background: #475569; color: #fff; }
        .ftth-measure-act.ftth-measure-otdr-act { border-color: #475569; background: #64748b; color: #fff; }
        .ftth-measure-act.ftth-measure-otdr-act:hover { background: #475569; color: #fff; }
        #ftthMeasureSelesaiBtn { background: #64748b; border-color: #475569; color: #fff; }
        #ftthMeasureSelesaiBtn:hover { background: #475569; }
        #ftthMeasureHapusBtn { background: #dc2626; border-color: #b91c1c; color: #fff; }
        #ftthMeasureHapusBtn:hover { background: #b91c1c; }
        .ftth-cable-edit {
            top: auto; bottom: 16px; left: 50%; right: auto; transform: translateX(-50%);
            width: 320px; border-color: rgba(56,189,248,0.45);
        }
        .ftth-cable-edit .ftth-measure-result-title { color: #38bdf8; }
        .ftth-cable-ctl-row { display: flex; align-items: center; gap: 10px; margin-top: 10px; flex-wrap: wrap; }
        .ftth-cable-ctl { display: flex; align-items: center; gap: 6px; font-size: 10.5px; color: #cbd5e1; font-weight: 600; }
        .ftth-cable-ctl input[type="color"] { width: 30px; height: 24px; padding: 0; border: 1px solid rgba(148,163,184,0.4); border-radius: 6px; background: none; cursor: pointer; }
        .ftth-cable-ctl input[type="range"] { width: 90px; accent-color: #38bdf8; }
        .ftth-cable-ctl b { font-size: 11px; color: #7dd3fc; min-width: 14px; text-align: right; }
        .ftth-cable-ctl-check { cursor: pointer; user-select: none; }
        .ftth-cable-ctl-check input { accent-color: #38bdf8; }

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

        /* ── Mode OTDR: input jarak + cek + reset titik ── */
        .ftth-otdr-route-box {
            margin-top: 9px; padding: 5px 8px;
            border: 1px solid rgba(56,189,248,0.35); background: rgba(56,189,248,0.10);
            border-radius: 8px; display: flex; align-items: center; justify-content: center; gap: 7px;
        }
        .ftth-otdr-route-box .lbl { font-size: 9.5px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: rgba(224,242,254,0.65); }
        .ftth-otdr-route-box .val { font-size: 12.5px; font-weight: 800; color: #38bdf8; }
        body.ftth-light .ftth-otdr-route-box { background: #eff6ff; border-color: #93c5fd; }
        body.ftth-light .ftth-otdr-route-box .lbl { color: #475569; }
        body.ftth-light .ftth-otdr-route-box .val { color: #0284c7; }
        .ftth-otdr-fault-coord {
            margin-top: 6px; padding: 5px 8px;
            background: rgba(249,115,22,0.12); border: 1px dashed rgba(249,115,22,0.5);
            border-radius: 8px; text-align: center;
            font-size: 10.5px; font-weight: 600; color: #fdba74;
        }
        body.ftth-light .ftth-otdr-fault-coord { background: #fff7ed; border-color: #fb923c; color: #c2410c; }
        .ftth-otdr-input-row { display: flex; gap: 6px; margin-top: 9px; }
        .ftth-otdr-input-row input {
            flex: 1; min-width: 0;
            background: rgba(7,17,31,0.6); border: 1px solid rgba(56,189,248,0.35);
            border-radius: 8px; padding: 7px 10px; color: #e0f2fe;
            font-size: 12px; outline: none;
        }
        .ftth-otdr-input-row input:focus { border-color: #38bdf8; }
        .ftth-measure-act.ftth-measure-otdr-act.ftth-otdr-cek,
        body.ftth-light .ftth-measure-act.ftth-measure-otdr-act.ftth-otdr-cek {
            flex: 0 0 auto; white-space: nowrap;
            padding-left: 12px; padding-right: 12px;
            background: #22c55e; border-color: #15803d; color: #fff;
        }
        .ftth-measure-act.ftth-measure-otdr-act.ftth-otdr-cek:hover,
        body.ftth-light .ftth-measure-act.ftth-measure-otdr-act.ftth-otdr-cek:hover {
            background: #16a34a; border-color: #14532d; color: #fff;
        }
        .ftth-otdr-reset {
            width: 100%; margin-top: 9px;
            background: #ef4444; border: none; color: #fff;
            font-weight: 700; font-size: 10.5px; padding: 5px 0;
            border-radius: 8px; cursor: pointer; transition: background .15s ease;
        }
        .ftth-otdr-reset:hover { background: #dc2626; }
        body.ftth-light .ftth-otdr-reset:hover { background: #b91c1c; }
        body.ftth-light .ftth-otdr-input-row input { background: #fff; border-color: #93c5fd; color: #0f172a; }
        .ftth-measure-result.ftth-measure-otdr .ftth-measure-result-actions { display: none; }
        .ftth-measure-hint-ok { color: #86efac; }
        .ftth-measure-hint-warn { color: #fca5a5; }
        .ftth-otdr-fault-point { background: #f97316; box-shadow: 0 0 0 2px rgba(249,115,22,0.55), 0 2px 8px rgba(0,0,0,0.5); }
        .ftth-otdr-fault-label {
            font-size: 10px; font-weight: 700; color: #fff; white-space: nowrap;
            padding: 2px 7px; border-radius: 7px; border: none;
            background: rgba(249,115,22,0.95);
            box-shadow: 0 4px 12px rgba(0,0,0,0.4);
        }

        /* ── Kalkulator Redaman: kuping tab + sliding ref panel ── */
        .ftth-calc-backdrop {
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .ftth-calc-wrap {
            position: relative;
            width: 380px;
            height: 480px;
            will-change: transform;
        }
        .ftth-modal-card.ftth-calc-card {
            position: absolute;
            right: 0; top: 0;
            width: 380px;
            height: auto;
            max-height: calc(100vh - 24px);
            z-index: 3;
            left: auto; transform: none;
            overflow: visible;
            border-color: rgba(251,146,60,0.35);
            transition: box-shadow .3s;
            box-sizing: border-box;
        }
        .ftth-calc-card .ftth-modal-body {
            overflow-y: hidden;
            overflow-x: hidden;
            flex: 1;
            min-height: 0;
        }
        .ftth-calc-adv-mode .ftth-modal-body {
            overflow: hidden;
        }
        /* Compact layout di Advanced Mode agar seluruh konten muat tanpa scroll */
        .ftth-calc-adv-mode .ftth-calc-form { gap: 4px; }
        .ftth-calc-adv-mode .ftth-calc-field { gap: 2px; }
        .ftth-calc-adv-mode .ftth-calc-hint { display: none; }
        .ftth-calc-adv-mode .ftth-calc-label { font-size: 9px; }
        .ftth-calc-adv-mode .ftth-calc-row { gap: 6px; }
        .ftth-calc-adv-mode .ftth-calc-card input[type="number"],
        .ftth-calc-adv-mode .ftth-calc-card select { padding: 3px 8px; font-size: 11px; }
        .ftth-calc-adv-mode .ftth-stepper button { height: 26px; }
        .ftth-calc-adv-mode .ftth-calc-mode { padding: 2px; }
        .ftth-calc-adv-mode #fcAdvFields .ftth-calc-row { margin-bottom: 4px; }
        .ftth-calc-adv-mode .ftth-calc-result { padding: 7px; gap: 4px; }
        .ftth-calc-adv-mode .ftth-calc-result-title { font-size: 9.5px; padding-bottom: 5px; }
        .ftth-calc-adv-mode .ftth-calc-ont-power { padding: 6px 12px; gap: 3px; }
        .ftth-calc-adv-mode .ftth-calc-ont-power b { font-size: 17px; }
        .ftth-calc-adv-mode .ftth-calc-status-inner { margin-top: 4px; padding: 4px 8px; font-size: 9px; }
        .ftth-calc-adv-mode .ftth-calc-detail { gap: 2px; }
        .ftth-calc-adv-mode .ftth-calc-detail-row { font-size: 9.5px; padding: 2px 0; }
        .ftth-calc-adv-mode .ftth-calc-detail-row b { font-size: 10px; }
        .ftth-calc-adv-mode .ftth-calc-detail-total { padding: 4px 8px; margin-top: 4px; }
        .ftth-calc-adv-mode .ftth-calc-detail-total b { font-size: 11px; }
        .ftth-calc-adv-mode .ftth-calc-detail-odp { padding: 4px 8px; }
        .ftth-calc-adv-mode .ftth-calc-detail-row + .ftth-calc-detail-row { padding-top: 3px; }
        .ftth-calc-adv-mode .ftth-calc-note { font-size: 9px; padding: 6px 10px; }
        .ftth-calc-card .ftth-modal-head > i { color: #fb923c; }
        .ftth-calc-card .ftth-modal-head,
        .ftth-calc-ref-title {
            box-sizing: border-box;
            line-height: 1.35;
            height: 32px;
            min-height: 32px;
            padding: 7px 12px;
            border-bottom-color: rgba(251,146,60,0.25) !important;
        }
        .ftth-calc-ref { color: #fff; }
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
        .ftth-calc-card select option[value=""] { color: #64748b; font-style: italic !important; font-size: 9px !important; }
        .ftth-calc-card select.ftth-placeholder { color: rgba(255,255,255,0.45); font-weight: 400; font-style: italic !important; font-size: 9px !important; }
        .ftth-calc-card input::placeholder { font-size: 9px !important; font-style: italic !important; color: rgba(255,255,255,0.45) !important; }
        .ftth-calc-card input[type="number"] { color-scheme: dark; }
        .ftth-calc-inline { display: flex; align-items: center; gap: 6px; }
        .ftth-calc-inline > input { flex: 1; min-width: 0; }
        .ftth-calc-inline .ftth-calc-unit { flex: 0 0 62px; }
        .ftth-stepper { display: flex; align-items: center; gap: 5px; }
        .ftth-stepper button {
            width: 30px;
            height: 32px;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 7px;
            border: 1px solid rgba(251,146,60,0.35);
            background: rgba(251,146,60,0.12);
            color: #fdba74;
            font-size: 17px;
            font-weight: 700;
            line-height: 1;
            cursor: pointer;
        }
        .ftth-stepper button:hover { background: rgba(251,146,60,0.28); }
        .ftth-stepper input {
            flex: 1;
            min-width: 0;
            height: 32px;
            text-align: center;
            -moz-appearance: textfield;
        }
        .ftth-stepper input::-webkit-outer-spin-button,
        .ftth-stepper input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        /* ── Mode toggle buttons ── */
        .ftth-calc-mode {
            display: flex;
            gap: 4px;
            padding: 3px;
            border-radius: 9px;
            background: rgba(251,146,60,0.08);
            border: 1px solid rgba(251,146,60,0.18);
        }
        .ftth-calc-mode-btn {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 5px 8px;
            border-radius: 7px;
            border: 1px solid transparent;
            background: transparent;
            color: rgba(255,255,255,0.5);
            font-size: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
            white-space: nowrap;
        }
        .ftth-calc-mode-btn i { font-size: 9px; }
        .ftth-calc-mode-btn:hover { filter: brightness(1.08); }
        .ftth-calc-mode-btn.active {
            box-shadow: none;
        }
        #fcModeSimple { background: #2563eb; border: 1px solid transparent; color: #fff; }
        #fcModeSimple:not(.active) { background: #0c1730; }
        #fcModeSimple:hover { background: #3b82f6; }
        #fcModeAdv { background: #16a34a; border: 1px solid transparent; color: #fff; }
        #fcModeAdv:not(.active) { background: #0c2018; }
        #fcModeAdv:hover { background: #22c55e; }
        /* ── Advanced fields hide/show ── */
        .ftth-calc-adv { margin-top: 0; }
        .ftth-calc-adv[style*="display: none"] { margin-top: 0; }
        /* ── Hasil Kalkulator section ── */
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
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 10.5px;
            font-weight: 800;
            color: #FFFFFF;
            letter-spacing: .06em;
            text-transform: uppercase;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(251,146,60,0.2);
        }
        .ftth-calc-result-title > i { color: #fdba74; margin-right: 6px; }
        .ftth-calc-ont-power {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 10px 14px;
            border-radius: 9px;
            background: rgba(251,146,60,0.08);
            border: 1px solid rgba(251,146,60,0.18);
        }
        .ftth-calc-ont-power .ftth-calc-ont-label { display: inline-flex; align-items: center; gap: 5px; font-size: 10px; color: rgba(255,255,255,0.65); font-weight: 600; letter-spacing: .03em; }
        .ftth-calc-ont-power b { font-size: 22px; font-weight: 800; font-variant-numeric: tabular-nums; line-height: 1.2; color: #fff; }
        .ftth-calc-status-inner {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            margin-top: 6px;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 700;
            border: 1px solid;
        }
        .ftth-calc-status-inner .ftth-calc-status { display: inline-flex; align-items: center; gap: 5px; }
        .ftth-calc-status-inner .ftth-calc-status i { font-size: 7px; }
        /* status colors — power box */
        .ftth-calc-ont-power.status-optimal { background: rgba(34,197,94,0.10); border-color: rgba(34,197,94,0.35); }
        .ftth-calc-ont-power.status-optimal b { color: #4ade80; }
        .ftth-calc-ont-power.status-strong { background: rgba(250,204,21,0.10); border-color: rgba(250,204,21,0.35); }
        .ftth-calc-ont-power.status-strong b { color: #facc15; }
        .ftth-calc-ont-power.status-warn { background: rgba(251,191,36,0.10); border-color: rgba(251,191,36,0.35); }
        .ftth-calc-ont-power.status-warn b { color: #fbbf24; }
        .ftth-calc-ont-power.status-bad { background: rgba(248,113,113,0.10); border-color: rgba(248,113,113,0.35); }
        .ftth-calc-ont-power.status-bad b { color: #f87171; }
        /* status colors — inner status card */
        .ftth-calc-status-inner.status-optimal { background: rgba(34,197,94,0.10); border-color: rgba(34,197,94,0.35); color: #4ade80; }
        .ftth-calc-status-inner.status-strong { background: rgba(250,204,21,0.10); border-color: rgba(250,204,21,0.35); color: #facc15; }
        .ftth-calc-status-inner.status-warn { background: rgba(251,191,36,0.10); border-color: rgba(251,191,36,0.35); color: #fbbf24; }
        .ftth-calc-status-inner.status-bad { background: rgba(248,113,113,0.10); border-color: rgba(248,113,113,0.35); color: #f87171; }
        .ftth-calc-detail {
            display: flex;
            flex-direction: column;
            gap: 3px;
            margin-top: 2px;
        }
        .ftth-calc-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10.5px;
            color: rgba(255,255,255,0.6);
            padding: 3px 0;
        }
        .ftth-calc-detail-row b { font-size: 11px; color: rgba(255,255,255,0.8); font-variant-numeric: tabular-nums; }
        .ftth-calc-detail-sm { font-size: 9.5px; }
        .ftth-calc-detail-sm b { font-size: 10px; color: rgba(255,255,255,0.6); }
        .ftth-calc-detail-total {
            padding: 6px 8px;
            margin-top: 6px;
            border-radius: 6px;
            border: 1px solid rgba(251,146,60,0.2);
            background: rgba(251,146,60,0.06);
        }
        .ftth-calc-detail-total b { font-size: 12px; font-weight: 700; }
        .ftth-calc-detail-total.status-optimal b { color: #4ade80; }
        .ftth-calc-detail-total.status-strong b { color: #facc15; }
        .ftth-calc-detail-total.status-warn b { color: #fbbf24; }
        .ftth-calc-detail-total.status-bad b { color: #f87171; }
        .ftth-calc-detail-odp {
            display: block;
            margin-top: 6px;
            padding: 2px 0 0;
            color: #93c5fd;
        }
        .ftth-calc-detail-odp b { font-size: 12px; font-weight: 700; color: #60a5fa !important; }
        .status-bg-optimal { background: rgba(34,197,94,0.10) !important; border-color: rgba(34,197,94,0.35) !important; }
        .status-bg-strong { background: rgba(250,204,21,0.10) !important; border-color: rgba(250,204,21,0.35) !important; }
        .status-bg-warn { background: rgba(251,191,36,0.10) !important; border-color: rgba(251,191,36,0.35) !important; }
        .status-bg-bad { background: rgba(248,113,113,0.10) !important; border-color: rgba(248,113,113,0.35) !important; }
        .ftth-calc-detail-row + .ftth-calc-detail-row { border-top: 1px dashed rgba(251,146,60,0.15); padding-top: 4px; }
        .ftth-calc-detail-total,
        .ftth-calc-detail-odp { border-top: none !important; padding-top: 0 !important; }
        .ftth-calc-adv-only { display: none; }
        .ftth-calc-adv-mode .ftth-calc-adv-only { display: flex; }
        .ftth-calc-note {
            margin-top: auto;
            font-size: 8px;
            color: rgba(255,255,255,0.45);
            line-height: 1.4;
            padding: 8px 4px 4px;
            text-align: center;
            border-top: 1px solid rgba(251,146,60,0.12);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex-shrink: 0;
        }
        /* ── Reference tables panel (kiri, sliding dari balik card) ── */
        .ftth-calc-ref {
            position: absolute;
            right: 100%;
            top: 0;
            width: 380px; height: 480px;
            z-index: 2;
            background: rgba(10,20,38,0.97);
            border: 1px solid rgba(251,146,60,0.35);
            border-radius: 14px;
            display: flex;
            flex-direction: column;
            pointer-events: none;
            visibility: hidden;
            transform: translateX(100%);
            transition: transform .38s cubic-bezier(.4,0,.2,1), visibility 0s .38s;
            box-sizing: border-box;
        }
        .ftth-calc-wrap.ref-open .ftth-calc-ref {
            pointer-events: auto;
            visibility: visible;
            transform: translateX(0);
            transition: transform .38s cubic-bezier(.4,0,.2,1), visibility 0s 0s;
        }
        .ftth-calc-ref .ftth-calc-ref-title > i { font-size: 12px; color: #fb923c !important; }
        .ftth-calc-ref-body {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            scrollbar-width: thin;
            scrollbar-color: rgba(251,146,60,0.25) transparent;
        }
        .ftth-calc-ref-body::-webkit-scrollbar { width: 4px; }
        .ftth-calc-ref-body::-webkit-scrollbar-thumb { background: rgba(251,146,60,0.35); border-radius: 4px; }
        .ftth-calc-ref-body::-webkit-scrollbar-corner { background: transparent; }
        .ftth-calc-ref-close {
            flex-shrink: 0;
            box-sizing: border-box;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            padding: 9px 0;
            border: 1px solid rgba(100,116,139,0.3);
            border-top: 1px solid rgba(100,116,139,0.3);
            border-radius: 0 0 13px 13px;
            background: #64748b;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s, color .2s;
        }
        .ftth-calc-ref-close:hover { background: #475569; color: #fff; }
        .ftth-calc-wrap.ref-open .ftth-calc-kuping { display: none; }
        .ftth-calc-ref-section {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .ftth-calc-ref-label {
            font-size: 9.5px;
            font-weight: 700;
            color: rgba(255,255,255,0.7);
            letter-spacing: .04em;
            text-transform: uppercase;
            margin-bottom: 2px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .ftth-calc-ref-label > i { font-size: 9px; color: #fb923c; }
        .ftth-calc-ref-tbl {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
        }
        .ftth-calc-ref-tbl th {
            background: rgba(251,146,60,0.12);
            color: #fdba74;
            font-weight: 700;
            padding: 4px 6px;
            text-align: left;
            border-bottom: 1px solid rgba(251,146,60,0.2);
            white-space: nowrap;
        }
        .ftth-calc-ref-tbl td {
            padding: 3px 6px;
            color: rgba(255,255,255,0.75);
            border-bottom: 1px solid rgba(251,146,60,0.08);
            white-space: nowrap;
        }
        .ftth-calc-ref-tbl tr:hover td { background: rgba(251,146,60,0.06); }
        .ftth-calc-ref-tbl .val { color: #facc15; font-weight: 600; font-variant-numeric: tabular-nums; }
        .ftth-calc-ref-comp {
            display: flex;
            flex-direction: column;
            gap: 3px;
            padding: 7px 8px;
            border-radius: 8px;
            background: rgba(251,146,60,0.06);
            border: 1px solid rgba(251,146,60,0.15);
        }
        .ftth-calc-ref-comp-row {
            display: flex;
            justify-content: space-between;
            font-size: 9.5px;
            color: rgba(255,255,255,0.65);
        }
        .ftth-calc-ref-comp-row b { color: #facc15; font-variant-numeric: tabular-nums; }
        /* ── Kuping tab (Ref button, kiri card, menyatu dengan card) ── */
        .ftth-calc-kuping {
            position: absolute;
            left: -27px;
            top: 32px;
            z-index: 4;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 27px;
            padding: 8px 4px;
            border: 1px solid rgba(251,146,60,0.35);
            border-right: none;
            border-radius: 8px 0 0 8px;
            background: rgba(10,20,38,0.97);
            color: #fdba74;
            cursor: pointer;
            transition: all .2s ease;
        }
        .ftth-calc-kuping i {
            font-size: 11px;
        }
        .ftth-calc-kuping .ftth-calc-kuping-label {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .08em;
            white-space: nowrap;
            line-height: 1.1;
        }
        .ftth-calc-kuping:hover {
            background: rgba(30,41,59,0.97);
            color: #fff;
            border-color: rgba(251,146,60,0.55);
            box-shadow: -2px 0 12px rgba(251,146,60,0.15);
        }
        .ftth-calc-wrap.ref-open .ftth-calc-kuping {
            background: rgba(251,146,60,0.12);
            color: #fb923c;
            border-color: rgba(251,146,60,0.45);
        }
        /* ── Ref panel toggle button (old, kept hidden) ── */
        .ftth-calc-ref-toggle { display: none; }
        /* ── Ref panel hide/show (legacy, now handled by ref-open class) ── */
        .ftth-calc-ref-hide { display: none !important; }

        /* ── Visibility card ── */
        .ftth-modal-card.ftth-vis-card { width: 344px; height: auto; max-height: calc(100vh - 48px); }
        .ftth-vis-card .ftth-modal-head > i { color: #06b6d4; }
        .ftth-vis-card .ftth-modal-body {
            flex: 1 1 auto;
            min-height: 0;
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
        .ftth-vis-collapsible { display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; }
        .ftth-vis-collapsible .ftth-vis-section-label { display: inline-flex; align-items: center; gap: 6px; }
        .ftth-vis-chevron { font-size: 10px; transition: transform .2s; color: rgba(255,255,255,0.6); }
        .ftth-vis-collapsible.open .ftth-vis-chevron { transform: rotate(180deg); }
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
            flex-shrink: 0;
            font-style: italic;
            font-size: 9.5px;
            color: rgba(255,255,255,0.45);
            line-height: 1.5;
            background: rgba(7,17,31,0.55);
            border-top: 1px solid rgba(6,182,212,0.3);
            border-radius: 0 0 14px 14px;
            padding: 8px 14px;
        }
        .ftth-vis-note > i { color: #22d3ee; margin-right: 4px; }

        /* ── Card Pengaturan User ── */
        .ftth-modal-card.ftth-users-card { width: 360px; height: auto; max-height: calc(100vh - 24px); }
        .ftth-users-card .ftth-modal-body { padding: 10px 12px; gap: 10px; }
        .ftth-users-list { display: flex; flex-direction: column; gap: 6px; }
        .ftth-user-row {
            display: flex; align-items: center; gap: 8px;
            padding: 8px 10px; border-radius: 9px;
            background: #152138; border: 1px solid rgba(148,163,184,0.18);
        }
        .ftth-user-main { flex: 1 1 auto; min-width: 0; }
        .ftth-user-name { font-size: 12px; font-weight: 700; color: #e879f9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ftth-user-role {
            display: inline-block; margin-top: 2px;
            font-size: 9px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
            padding: 1px 7px; border-radius: 999px;
            background: rgba(232,121,249,0.16); color: #e879f9; border: 1px solid rgba(232,121,249,0.35);
        }
        .ftth-user-actions { display: flex; gap: 6px; flex-shrink: 0; }
        .ftth-user-edit, .ftth-user-del, .ftth-user-add, .ftth-user-close {
            border: none; border-radius: 8px; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            font-size: 11px; font-weight: 600; color: #fff;
            padding: 7px 10px; transition: background .15s;
        }
        .ftth-user-edit { background: #2563eb; }
        .ftth-user-edit:hover { background: #3b82f6; }
        .ftth-user-del { background: #dc2626; }
        .ftth-user-del:hover { background: #ef4444; }
        .ftth-user-add { background: #2563eb; }
        .ftth-user-add:hover { background: #3b82f6; }
        .ftth-user-close { background: #64748b; }
        .ftth-user-close:hover { background: #475569; }
        .ftth-user-edit i, .ftth-user-del i, .ftth-user-add i { font-size: 12px; }
        .ftth-users-foot {
            flex-shrink: 0; display: flex; flex-direction: column; gap: 8px;
            padding: 10px 12px; border-top: 1px solid rgba(148,163,184,0.2);
        }
        .ftth-users-foot .ftth-user-add, .ftth-users-foot .ftth-user-close { width: 100%; }
        .ftth-user-empty { text-align: center; color: #a78bfa; font-size: 11px; padding: 14px 0; }
        .ftth-user-form {
            display: flex; flex-direction: column; gap: 8px; padding: 10px;
            border-radius: 10px; background: rgba(7,17,31,0.5); border: 1px solid rgba(148,163,184,0.18);
        }
        .ftth-user-field { display: flex; flex-direction: column; gap: 3px; font-size: 10.5px; color: rgba(255,255,255,0.65); }
        .ftth-user-field input, .ftth-user-field select {
            width: 100%; padding: 7px 9px; border-radius: 8px;
            border: 1px solid rgba(148,163,184,0.25); background: #0f172a; color: #e2e8f0; font-size: 12px;
        }
        .ftth-user-input-wrap { position: relative; display: flex; align-items: center; }
        .ftth-user-input-wrap input { padding-right: 34px; }
        .ftth-user-pw-toggle {
            position: absolute; right: 7px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; padding: 2px;
            color: rgba(255,255,255,0.5); font-size: 13px; line-height: 1;
        }
        .ftth-user-pw-toggle:hover { color: #fff; }
        body.ftth-light .ftth-user-pw-toggle { color: #64748b; }
        body.ftth-light .ftth-user-pw-toggle:hover { color: #334155; }
        body.ftth-light .ftth-user-row { background: #eef1f5; border-color: #dbe1e8; }
        body.ftth-light .ftth-user-name { color: #a21caf; }
        body.ftth-light .ftth-user-role { background: rgba(162,28,175,0.12); color: #a21caf; border-color: rgba(162,28,175,0.3); }
        body.ftth-light .ftth-user-field input, body.ftth-light .ftth-user-field select { background: #fff; color: #334155; border-color: #cbd5e1; }
        body.ftth-light .ftth-user-form { background: #f1f5f9; border-color: #dbe1e8; }
        body.ftth-light .ftth-user-empty { color: #8b5cf6; }
        .ftth-user-perm-title { font-size: 11px; font-weight: 700; color: #e879f9; margin-top: 4px; }
        .ftth-user-perm-title small { color: rgba(255,255,255,0.45); font-weight: 500; }
        .ftth-user-perm-list { display: flex; flex-direction: column; gap: 5px; padding: 8px 10px; border-radius: 8px; background: rgba(7,17,31,0.4); border: 1px solid rgba(148,163,184,0.18); }
        .ftth-user-perm { display: flex; align-items: center; gap: 7px; font-size: 11px; color: rgba(255,255,255,0.8); cursor: pointer; }
        .ftth-user-perm input { accent-color: #2563eb; width: 14px; height: 14px; }
        body.ftth-light .ftth-user-perm-title small { color: #64748b; }
        body.ftth-light .ftth-user-perm-list { background: #fff; border-color: #dbe1e8; }
        body.ftth-light .ftth-user-perm { color: #334155; }
        .ftth-perm-denied { display: none !important; }

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
            display: grid;
            grid-template-columns: repeat(2, max-content);
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
            min-width: 0;
            padding: 4px 7px;
            white-space: nowrap;
            overflow: hidden;
        }
        .ftth-status-item:nth-child(even) { border-left: 1px solid rgba(96,165,250,0.35); }
        .ftth-status-item:nth-child(n+3) { border-top: 1px solid rgba(96,165,250,0.35); }
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
            background: rgba(0,0,0,0.3);
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
            background: rgba(7,17,31,0.55);
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
            background: #64748b;
            border: none;
            color: #fff;
            font-size: 15px;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 6px;
        }
        .ftth-modal-close:hover { color: #fff; background: #475569; }
        .ftth-modal-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 10px 12px;
            overflow-y: auto;
        }
        .ftth-form { display: grid; gap: 4px; }
        /* Form 2 kolom (card Sync OLT): IP+Port sebaris, Username+Password sebaris */
        .ftth-form-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 8px; }
        .ftth-form-cell { display: grid; gap: 3px; }
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
        /* Kotak grafik live trafik WAN-ISP (card Sync Mikrotik) */
        #ftthMikrotikCard { height: auto; }
        #ftthMikrotikCard .ftth-modal-body { overflow: hidden; scrollbar-width: none; }
        #ftthMikrotikCard .ftth-modal-body::-webkit-scrollbar { display: none; }
        #ftthOltCard { height: auto; }
        #ftthOltCard .ftth-modal-body { overflow: hidden; scrollbar-width: none; }
        #ftthOltCard .ftth-modal-body::-webkit-scrollbar { display: none; }
        .ftth-mt-wan {
            margin-top: 10px;
            padding: 6px 8px;
            border-radius: 10px;
            background: rgba(7,17,31,0.7);
            border: 1px solid rgba(96,165,250,0.25);
        }
        .ftth-mt-wan-head { display: flex; align-items: center; gap: 6px; font-size: 9px; color: rgba(255,255,255,0.75); flex-wrap: wrap; }
        .ftth-mt-wan-title { font-weight: 700; color: #93c5fd; white-space: nowrap; }
        .ftth-mt-wan-title i { font-size: 8.5px; }
        .ftth-mt-wan-rate { display: inline-flex; align-items: center; gap: 3px; white-space: nowrap; margin-left: auto; font-size: 9px; }
        .ftth-mt-wan-rate.tx { margin-left: 0; }
        .ftth-mt-wan-rate b { min-width: 44px; text-align: right; color: #fff; font-size: 8.5px; font-weight: 700; }
        .ftth-mt-wan-rate i { width: 5px; height: 5px; border-radius: 50%; display: inline-block; }
        .ftth-mt-wan-rate.rx i { background: #22c55e; box-shadow: 0 0 4px rgba(34,197,94,0.8); }
        .ftth-mt-wan-rate.tx i { background: #3b82f6; box-shadow: 0 0 4px rgba(59,130,246,0.8); }
        .ftth-mt-wan-chart { position: relative; height: 62px; margin-top: 5px; }
        .ftth-mt-wan-chart canvas { width: 100%; height: 100%; display: block; }
        .ftth-mt-wan-status { margin-top: 4px; font-size: 8.5px; color: #94a3b8; }
        .ftth-mt-wan-status.live { color: #4ade80; }
        .ftth-mt-wan-status.off { color: #f87171; }
        body.ftth-light .ftth-mt-wan { background: #fff; border-color: rgba(30,58,100,0.18); }
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
        .ftth-router-empty { font-size: 12px; color: #a78bfa; text-align: center; padding: 8px 0; }
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

        /* ── Card Daftar PPPoE ── */
        #ftthQueueCard { width: 380px; height: 460px; }
        #ftthQueueCard .ftth-modal-body { padding: 0; gap: 0; }
        #ftthQueueCard .ftth-modal-body::-webkit-scrollbar { display: none; }

        /* ── Dropdown Queue (PPPoE / Hotspot) ── */
        #ftthQueueCard, #ftthHotspotCard { background: #0a1426; }
        .ftth-dropdown { position: relative; display: inline-flex; }
        .ftth-btn-caret { font-size: 9px; margin-left: 2px; opacity: .8; }
        .ftth-dropdown-menu {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            min-width: 184px;
            background: #0f172a;
            border: 1px solid rgba(74,222,128,0.35);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            padding: 6px;
            z-index: 1300;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .ftth-dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            text-align: left;
            background: #152138;
            border: 1px solid rgba(74,222,128,0.2);
            color: #fff;
            padding: 9px 10px;
            border-radius: 9px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
        }
        .ftth-dropdown-item i { font-size: 15px; width: 20px; text-align: center; }
        .ftth-dropdown-item.ftth-dd-pppoe { border-color: rgba(34,211,238,0.25); }
        .ftth-dropdown-item.ftth-dd-pppoe > i { color: #22d3ee; }
        .ftth-dropdown-item.ftth-dd-pppoe:hover { background: #0e7490; border-color: #22d3ee; color: #fff; }
        .ftth-dropdown-item.ftth-dd-hotspot { border-color: rgba(251,146,60,0.25); }
        .ftth-dropdown-item.ftth-dd-hotspot > i { color: #fb923c; }
        .ftth-dropdown-item.ftth-dd-hotspot:hover { background: #c2410c; border-color: #fb923c; color: #fff; }

        /* ── Card Daftar Hotspot ── */
        #ftthHotspotCard { width: 380px; height: 460px; }
        #ftthHotspotCard .ftth-modal-body { padding: 0; gap: 0; }
        #ftthHotspotCard .ftth-modal-body::-webkit-scrollbar { display: none; }
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
        .ftth-queue-list-wrap {
            flex: 1;
            overflow-y: auto;
            padding: 10px 12px;
            scrollbar-width: thin;
            scrollbar-color: rgba(96,165,250,0.4) transparent;
        }
        .ftth-queue-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(15,23,42,0.6);
            border: 1px solid rgba(51,65,85,0.7);
            border-radius: 10px;
            padding: 9px 12px;
            margin-bottom: 8px;
            transition: transform .18s ease, border-color .18s ease, background .18s ease;
        }
        .ftth-queue-item:hover { transform: translateX(4px); background: rgba(30,41,59,0.75); border-color: rgba(96,165,250,0.45); }
        .ftth-queue-item-main { flex: 1 1 auto; min-width: 0; }
        .ftth-queue-item-name {
            font-size: 12.5px; font-weight: 700; color: #93c5fd;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .ftth-queue-item-ip {
            font-size: 11px; color: #94a3b8;
            font-family: ui-monospace, SFMono-Regular, Consolas, Menlo, monospace;
            margin-top: 2px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .ftth-queue-item-add {
            flex: 0 0 auto;
            background: rgba(45,212,191,0.16);
            border: 1px solid rgba(45,212,191,0.4);
            color: #5eead4;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .04em;
            cursor: pointer;
            transition: all .15s;
            white-space: nowrap;
        }
        .ftth-queue-item-add:hover { background: rgba(45,212,191,0.32); color: #fff; }

        /* ── Card Backup & Restore ── */
        #ftthBackupCard { width: 430px; height: auto; }
        #ftthBackupCard .ftth-modal-body { overflow: visible; gap: 8px; }
        .ftth-smtp-adv { margin-top: 2px; }
        .ftth-smtp-adv summary { cursor: pointer; font-size: 11px; font-weight: 600; color: #94a3b8; padding: 4px 0; user-select: none; list-style: none; }
        .ftth-smtp-adv summary::-webkit-details-marker { display: none; }
        .ftth-smtp-adv summary:hover, .ftth-smtp-adv[open] summary { color: #a78bfa; }
        .ftth-smtp-adv .ftth-bs-form { margin-top: 6px; padding-top: 6px; border-top: 1px dashed rgba(148,163,184,0.25); }
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
        #ftthAddDeviceTitle { color: #f59e0b; }
        .ftth-pppoe-edit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px; height: 20px;
            margin-left: 6px;
            vertical-align: middle;
            border: 1px solid rgba(245,158,11,0.5);
            background: rgba(245,158,11,0.12);
            color: #fb923c;
            border-radius: 6px;
            cursor: pointer;
            transition: all .15s;
            font-size: 9px;
        }
        .ftth-pppoe-edit:hover { background: rgba(245,158,11,0.32); color: #fff; }
        .ftth-pppoe-edit.active { background: rgba(245,158,11,0.35); box-shadow: 0 0 0 1px rgba(245,158,11,0.5); }
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
        .ftth-btn-batal { background: #64748b; border-color: #475569; }
        .ftth-btn-batal:hover { background: #475569; }
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
        .ftth-tag-wrap { position: relative; display: flex; flex-direction: column; align-items: center; }
        .ftth-tag-note {
            margin-bottom: 2px;
            padding: 3px 9px;
            border-radius: 7px;
            background: rgba(7,17,31,0.92);
            border: 1px solid rgba(59,130,246,0.5);
            color: #93c5fd;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            box-shadow: 0 4px 14px rgba(0,0,0,0.35);
            pointer-events: none;
        }
        .ftth-tag-hand { font-size: 26px; color: #facc15; line-height: 1; text-shadow: 0 3px 9px rgba(0,0,0,0.7); animation: ftthHandPoint 0.9s ease-in-out infinite; }
        .ftth-tag-dot { font-size: 20px; color: #3b82f6; line-height: 1; text-shadow: 0 2px 8px rgba(0,0,0,0.7); }
        @keyframes ftthHandPoint {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(7px); }
        }

        /* ── Marker tangan penunjuk hasil pencarian koordinat:
              melayang di atas titik lalu menukik menunjuk ke bawah ── */
        .ftth-search-hand { background: none; border: none; }
        .ftth-search-hand-i {
            font-size: 36px; color: #facc15; line-height: 1;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.6));
            animation: ftthSearchHandDive 1s ease-in-out infinite;
            will-change: transform;
        }
        @keyframes ftthSearchHandDive {
            0%, 100% { transform: translateY(-16px) scale(1.04); opacity: .9; }
            55%      { transform: translateY(6px) scale(.98);   opacity: 1; }
        }

        /* ── Marker peta: icon perangkat + label (nama & lokasi) ── */
        .ftth-marker-label { background: none; border: none; text-align: center; }
        .ftth-onu-label {
            background: rgba(0,0,0,0.55);
            border: none;
            color: #f1f5f9;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
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
        .ftth-ic.blink-on .ftth-ic-i i { animation: ftth-blink-on 1.1s ease-in-out infinite; }
        .ftth-ic.blink-off .ftth-ic-i { border-color: rgba(239,68,68,0.9); }
        .ftth-ic.blink-off .ftth-ic-i i { animation: ftth-blink-off 1.1s ease-in-out infinite; }
        @keyframes ftth-blink-on {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: .25; transform: scale(.86); }
        }
        @keyframes ftth-blink-off {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: .28; transform: scale(.86); }
        }
        .ftth-ic .ftth-ic-i i.ftth-glow-olt { color: #ef4444; text-shadow: 0 0 8px rgba(239,68,68,0.95), 0 0 18px rgba(239,68,68,0.55); }
        .ftth-ic .ftth-ic-i i.ftth-glow-odc { color: #f97316; text-shadow: 0 0 8px rgba(249,115,22,0.95), 0 0 18px rgba(249,115,22,0.55); }
        .ftth-ic .ftth-ic-i i.ftth-glow-odp { color: #facc15; text-shadow: 0 0 8px rgba(250,204,21,0.95), 0 0 18px rgba(250,204,21,0.55); }
        .ftth-ic .ftth-ic-i i.ftth-glow-customer { color: #3b82f6; text-shadow: 0 0 8px rgba(59,130,246,0.95), 0 0 18px rgba(59,130,246,0.55); }
        .ftth-ic.ftth-marker-active .ftth-ic-i {
            border-color: #38bdf8 !important;
            box-shadow: 0 0 0 2px rgba(56,189,248,0.6), 0 0 12px rgba(56,189,248,0.4);
            transform: scale(1.18);
            transition: all 0.15s ease;
        }
        .ftth-ic.ftth-marker-active .ftth-ic-i i { color: #fff !important; }
        /* stroke-width sengaja TIDAK di-set di CSS: biarkan atribut Leaflet (weight)
           yang mengatur — CSS di sini akan menimpa ukuran kabel per-perangkat */
        .ftth-cable {
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }
        /* Glow kabel global (toggle toolbar): kabel memancarkan cahaya warnanya */
        .ftth-cables-glow .ftth-cable {
            filter: drop-shadow(0 0 2px var(--glowc, #38bdf8)) drop-shadow(0 0 7px var(--glowc, #38bdf8));
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
        .ftth-cable-anim-dash {
            stroke-dasharray: 10 8;
            animation: ftth-cable-flow 0.55s linear infinite;
        }
        .ftth-cable-anim-glow-fast { animation: ftth-cable-glow 1s ease-in-out infinite; }
        .ftth-cable-anim-glow-slow { animation: ftth-cable-glow 2.6s ease-in-out infinite; }
        @keyframes ftth-cable-glow {
            0%, 100% { opacity: 0.55; filter: drop-shadow(0 0 1px rgba(255,255,255,0.3)); }
            50% { opacity: 1; filter: drop-shadow(0 0 6px rgba(255,255,255,0.9)); }
        }

        /* ── Card properti kabel (slide-out dari balik card utama) ── */
        .ftth-cable-props {
            position: fixed; z-index: 9885; width: 252px;
            background: rgba(10,20,38,0.97); border: 1px solid rgba(96,165,250,0.35);
            border-radius: 14px; box-shadow: 0 16px 44px rgba(0,0,0,0.55);
            padding: 12px; display: none;
            transition: transform 0.28s cubic-bezier(0.22,1,0.36,1), opacity 0.28s ease;
        }
        .ftth-cable-props.behind { transform: translateX(-48px); opacity: 0; pointer-events: none; }
        .ftth-cable-props-head { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 800; color: #e2e8f0; }
        .ftth-cable-props-head span { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ftth-cable-props-head > i { color: #22d3ee; }
        .ftth-cable-props-head button { width: 24px; height: 24px; border-radius: 7px; border: none; background: #64748b; color: #fff; cursor: pointer; font-size: 12px; flex: none; }
        .ftth-cable-props-body { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
        .ftth-cable-props-body .ftth-cable-ctl { justify-content: space-between; font-size: 11px; }
        .ftth-cable-props-body .ftth-cable-ctl input[type="range"] { width: 118px; }
        .ftth-cable-props-body select { background: rgba(15,30,55,0.9); color: #e2e8f0; border: 1px solid rgba(148,163,184,0.35); border-radius: 7px; font-size: 11px; padding: 4px 6px; max-width: 150px; }
        body.ftth-light .ftth-cable-props { background: #fff; border-color: rgba(30,58,100,0.18); box-shadow: 0 16px 44px rgba(15,23,42,0.14); }
        body.ftth-light .ftth-cable-props-head { color: #1e293b; }
        body.ftth-light .ftth-cable-props-head button { background: #64748b; color: #fff; }
        body.ftth-light .ftth-cable-props-body select { background: #fff; color: #1e293b; }
        .ftth-cable-props-info { margin-top: 8px; font-size: 10.5px; color: #94a3b8; }
        .ftth-cable-props-actions { display: grid; grid-template-columns: 1fr 1fr 1.35fr; gap: 6px; margin-top: 10px; }
        .ftth-cable-props-actions .ftth-odc-btn-lg { font-size: 9.5px; letter-spacing: 0; padding: 6px 2px; gap: 3px; white-space: nowrap; }
        .ftth-cable-props-actions .ftth-odc-btn-lg i { font-size: 9.5px; }
        .ftth-cable-cancel-btn { width: 100%; margin-top: 6px; padding: 6px 0; border: none; border-radius: 7px; background: #64748b; color: #fff; font-size: 9.5px; font-weight: 700; cursor: pointer; transition: background .15s; }
        .ftth-cable-cancel-btn:hover { background: #475569; }
        /* Toggle geser Glow Kabel: klik geser kanan = aktif, kiri = mati */
        .ftth-glow-switch { position: relative; display: inline-block; width: 34px; height: 18px; flex: none; }
        .ftth-glow-switch input { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
        .ftth-glow-slider { position: absolute; inset: 0; display: block; background: rgba(148,163,184,0.35); border-radius: 999px; cursor: pointer; transition: background .2s ease; }
        .ftth-glow-slider::before { content: ''; position: absolute; left: 2px; top: 2px; width: 14px; height: 14px; background: #e2e8f0; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,0.4); transition: transform .2s ease; }
        .ftth-glow-switch input:checked + .ftth-glow-slider { background: #22c55e; box-shadow: 0 0 8px rgba(34,197,94,0.55); }
        .ftth-glow-switch input:checked + .ftth-glow-slider::before { transform: translateX(16px); background: #fff; }
        .ftth-glow-switch input:focus-visible + .ftth-glow-slider { outline: 2px solid #38bdf8; outline-offset: 1px; }
        body.ftth-light .ftth-glow-slider { background: rgba(100,116,139,0.35); }
        .ftth-kabel-btn { width: 100%; margin-top: 7px; }
        .ftth-reposition-bar {
            position: fixed; left: 50%; top: 56px; transform: translateX(-50%);
            z-index: 9995; display: none; align-items: center; gap: 10px; max-width: calc(100vw - 24px);
            background: rgba(0,0,0,0.6); border: 1px solid rgba(34,197,94,0.45);
            border-radius: 12px; padding: 8px 12px; font-size: 11.5px; font-weight: 700; color: #e2e8f0;
            box-shadow: 0 12px 40px rgba(0,0,0,0.55);
        }
        .ftth-reposition-bar button { padding: 3px 9px; border-radius: 6px; border: none; cursor: pointer; font-size: 9.5px; font-weight: 800; color: #fff; flex: none; }
        .ftth-reposition-bar .done { background: #64748b; }
        .ftth-reposition-bar .cancel { background: #64748b; }
        .ftth-cable-vx { background: none !important; border: none !important; }
        .ftth-cable-vx-i { width: 12px; height: 12px; border-radius: 50%; background: #fbbf24; border: 2px solid #0b1524; box-shadow: 0 0 6px rgba(0,0,0,0.5); cursor: move; }

        /* ── Master toggle animasi: matikan semua animasi peta sekaligus ── */
        body.ftth-anim-off * {
            animation: none !important;
        }

        /* ── List Perangkat ── */
        .ftth-devices-list-card { width: 340px; top: 66px; transform: translateX(-50%); max-height: calc(100vh - 130px); }
        .ftth-device-list { display: flex; flex-direction: column; gap: 3px; flex: 1 1 auto; min-height: 0; overflow-y: auto; overflow-x: hidden; padding-right: 6px; }
        .ftth-device-row {
            display: flex; align-items: center; gap: 4px;
            background: rgba(15,23,42,0.6);
            border: 1px solid rgba(51,65,85,0.7);
            border-radius: 8px;
            padding: 3px 6px;
            transition: transform .18s ease, border-color .18s ease, background .18s ease;
        }
        .ftth-device-row:hover { transform: translateX(5px); border-color: rgba(96,165,250,0.45); background: rgba(30,41,59,0.75); }
        .ftth-device-type-badge {
            flex: 0 0 auto;
            font-size: 9px; font-weight: 800;
            border-radius: 5px; padding: 3px 6px;
            letter-spacing: .04em;
            color: #020617;
        }
        .ftth-device-row-main { flex: 1 1 auto; min-width: 0; }
        .ftth-device-row-name {
            display: block;
            font-size: 11px; font-weight: 700; color: #f1f5f9;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            padding-left: 7px;
            border-left: 3px solid var(--fc, #60a5fa);
        }
        .ftth-device-row-sub { display: block; font-size: 9px; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ftth-device-row-del {
            flex: 0 0 auto;
            width: 20px; height: 20px;
            border-radius: 6px;
            border: 1px solid rgba(239,68,68,0.5);
            background: rgba(239,68,68,0.12);
            color: #fca5a5;
            font-size: 9px;
            cursor: pointer;
            transition: all .15s;
        }
        .ftth-device-row-del:hover { background: rgba(239,68,68,0.35); color: #fff; }
        .ftth-device-row-edit {
            flex: 0 0 auto;
            width: 20px; height: 20px;
            border-radius: 6px;
            border: 1px solid rgba(96,165,250,0.5);
            background: rgba(96,165,250,0.12);
            color: #93c5fd;
            font-size: 9px;
            cursor: pointer;
            transition: all .15s;
        }
        .ftth-device-row-edit:hover { background: rgba(96,165,250,0.35); color: #fff; }
        .ftth-device-row-main {
            cursor: pointer;
            border-radius: 6px;
            transition: background .15s;
        }
        .ftth-device-row-main:hover { background: rgba(96,165,250,0.08); }
        .ftth-device-row-status {
            flex: 0 0 auto;
            display: flex; align-items: center; gap: 3px;
            font-size: 8.5px; font-weight: 800; letter-spacing: .03em;
            padding: 2px 6px;
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
        .ftth-device-empty { text-align: center; color: #a78bfa; font-size: 12px; padding: 26px 10px; }
        .ftth-onu-loading { background: rgba(10,20,38,0.97); }
        body.ftth-light .ftth-onu-loading { background: #f6f7f9; }

        .ftth-a-loader { display: block; width: 36px; height: 34px; margin: 0 auto 6px; }
        .ftth-a-loader svg { width: 36px; height: 34px; }
        .ftth-map-loader {
            position: fixed;
            inset: 0;
            z-index: 1200;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: rgba(10,20,38,0.55);
            backdrop-filter: blur(1px);
            -webkit-backdrop-filter: blur(1px);
        }
        .ftth-map-loader[hidden] { display: none; }
        .ftth-map-loader-msg { font-size: 12px; color: #e2e8f0; letter-spacing: .03em; font-weight: 600; }
        body.ftth-light .ftth-map-loader { background: rgba(255,255,255,0.5); }
        body.ftth-light .ftth-map-loader-msg { color: #334155; }
        body.ftth-light .ftth-map-loader .ftth-a-chevron,
        body.ftth-light .ftth-map-loader .ftth-a-check { stroke: #334155; }
        body.ftth-light .ftth-map-loader .ftth-a-tip { fill: #334155; }
        .ftth-a-chevron {
            fill: none; stroke: #fff; stroke-width: 3.5;
            stroke-linecap: round; stroke-linejoin: round;
        }
        .ftth-a-check-group {
            transform-origin: 27px 28px;
            transform: rotate(7deg);
        }
        .ftth-a-check {
            fill: none; stroke: #fff; stroke-width: 3;
            stroke-linecap: round; stroke-linejoin: round;
            stroke-dasharray: 50; stroke-dashoffset: 50;
            animation: ftth-draw-chk 1.6s ease-in-out infinite;
        }
        .ftth-a-tip {
            fill: #fff;
            opacity: 0;
            animation: ftth-tip-pop 1.6s ease-in-out infinite;
        }
        @keyframes ftth-draw-chk {
            0%   { stroke-dashoffset: 50; }
            40%  { stroke-dashoffset: 0; }
            60%  { stroke-dashoffset: 0; }
            100% { stroke-dashoffset: -50; }
        }
        @keyframes ftth-tip-pop {
            0%, 40%  { opacity: 0; }
            48%      { opacity: 1; }
            58%      { opacity: 1; }
            68%      { opacity: 0; }
            100%     { opacity: 0; }
        }
        body.ftth-light .ftth-a-chevron  { stroke: #334155; }
        body.ftth-light .ftth-a-check    { stroke: #334155; }
        body.ftth-light .ftth-a-tip      { fill: #334155; }

        /* ── Scrollbar ramping (seluruh fitur map screen) ── */
        html { scrollbar-width: thin; scrollbar-color: #a855f7 rgba(15,23,42,0.4); }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #38bdf8, #a855f7); border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: linear-gradient(180deg, #7dd3fc, #c084fc); }

        /* ── Daftar Perangkat: kategori ── */
        .ftth-device-cats { display: grid; grid-template-columns: 1fr; gap: 6px; padding-right: 6px; }
        .ftth-dev-cat {
            position: relative;
            display: flex; align-items: center; gap: 8px;
            background: rgba(15,23,42,0.6);
            border: 1px solid rgba(51,65,85,0.7);
            border-left: 4px solid var(--c, #94a3b8);
            border-radius: 10px;
            padding: 6px 10px;
            cursor: pointer;
            overflow: hidden;
            transition: transform .2s ease, border-color .2s ease, background .2s ease, box-shadow .2s ease;
        }
        .ftth-dev-cat::before {
            content: '';
            position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
            background: var(--c, #94a3b8);
            box-shadow: 0 0 14px var(--c, #94a3b8);
            opacity: 0;
            transform: translateX(-14px);
            transition: transform .25s ease, opacity .25s ease;
        }
        .ftth-dev-cat:hover { transform: translateX(5px); background: rgba(30,41,59,0.78); box-shadow: 0 6px 18px rgba(0,0,0,0.35); }
        .ftth-dev-cat:hover::before { transform: translateX(0); opacity: 1; }
        .ftth-dev-cat-ic {
            flex: 0 0 auto;
            width: 30px; height: 30px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; color: var(--c);
            background: rgba(148,163,184,0.12);
            background: color-mix(in srgb, var(--c) 14%, transparent);
        }
        .ftth-dev-cat-body { flex: 1 1 auto; min-width: 0; }
        .ftth-dev-cat-name { font-size: 11px; font-weight: 800; color: #f1f5f9; }
        .ftth-dev-cat-jelajahi { font-size: 10px; color: #94a3b8; }
        .ftth-dev-cat-badge {
            position: absolute; top: 6px; right: 6px;
            min-width: 18px; height: 18px; padding: 0 5px;
            border-radius: 99px;
            display: flex; align-items: center; justify-content: center;
            background: var(--c); color: #020617;
            font-size: 10px; font-weight: 800;
        }

        /* ── Daftar Perangkat: jelajahi data ── */
        .ftth-device-browse { display: flex; flex-direction: column; gap: 8px; flex: 1 1 auto; min-height: 0; }
        .ftth-browse-toolbar { display: flex; align-items: center; gap: 8px; flex: 0 0 auto; }
        .ftth-browse-search {
            flex: 1 1 auto;
            display: flex; align-items: center; gap: 6px;
            background: rgba(15,23,42,0.85);
            border: 1px solid rgba(51,65,85,0.8); border-radius: 8px;
            padding: 6px 10px;
        }
        .ftth-browse-search i { font-size: 12px; color: #64748b; }
        .ftth-browse-search input { flex: 1 1 auto; min-width: 0; background: none; border: none; outline: none; color: #e2e8f0; font-size: 12px; }
        .ftth-browse-search input::placeholder { color: #64748b; }
        .ftth-browse-delall {
            flex: 0 0 auto;
            border: 1px solid rgba(239,68,68,0.5);
            background: rgba(239,68,68,0.12);
            color: #fca5a5;
            border-radius: 8px; padding: 7px 12px;
            font-size: 11.5px; font-weight: 700; cursor: pointer;
            transition: all .15s; white-space: nowrap;
        }
        .ftth-browse-delall:hover { background: rgba(239,68,68,0.35); color: #fff; }
        .ftth-browse-back {
            margin-left: auto;
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(15,23,42,0.85);
            border: 1px solid rgba(51,65,85,0.8); border-radius: 8px;
            color: #e2e8f0; font-size: 11.5px; font-weight: 600; cursor: pointer;
            padding: 6px 12px; transition: all .15s;
        }
        .ftth-browse-back:hover { background: rgba(59,130,246,0.25); border-color: rgba(59,130,246,0.5); }
        .ftth-browse-close {
            flex: 0 0 auto;
            width: 100%;
            border: 1px solid #475569;
            background: #64748b;
            color: #fff;
            border-radius: 10px; padding: 9px;
            font-size: 12.5px; font-weight: 700; cursor: pointer;
            transition: all .15s;
        }
        .ftth-browse-close:hover { background: #475569; color: #fff; }

        /* ── Card info perangkat (klik marker) ── */
         .ftth-detail-card {
            position: fixed;
            left: 0;
            top: 0;
            z-index: 9990;
            width: 300px;
            max-height: none;
            display: flex;
            flex-direction: column;
            background: rgba(10,20,38,0.96);
            border: 1px solid rgba(96,165,250,0.35);
            border-radius: 14px;
            box-shadow: 0 16px 44px rgba(0,0,0,0.55);
            color: #fff;
            font-size: 12px;
            overflow: visible;
            backdrop-filter: blur(8px);
        }
        .ftth-detail-head {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 9px 10px;
            border-bottom: 1px solid rgba(96,165,250,0.2);
            flex-shrink: 0;
            cursor: grab;
            user-select: none;
            touch-action: none;
        }
        .ftth-detail-grip { color: rgba(255,255,255,0.35); font-size: 12px; cursor: grab; }
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
            overflow-y: visible;
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

        /* ── ODC Detail Card ── */
        .ftth-detail-card.ftth-odc-card {
            width: 260px; padding: 12px; border-radius: 14px;
            background: rgba(10,18,36,0.97);
            border: 1px solid rgba(56,100,180,0.28);
            box-shadow: 0 12px 48px rgba(0,0,0,0.6), 0 0 0 1px rgba(56,100,180,0.12);
            max-height: none; overflow: visible;
        }
        .ftth-detail-card.ftth-odc-card .ftth-detail-body {
            overflow: visible; overflow-y: visible; max-height: none; padding: 0;
            scrollbar-width: none;
        }
        .ftth-detail-card.ftth-odc-card .ftth-detail-body::-webkit-scrollbar { display: none; }
        .ftth-odc-head {
            display: flex; align-items: center; gap: 10px;
            padding: 0 0 12px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            cursor: grab; user-select: none; touch-action: none;
        }
        .ftth-odc-line {
            width: 4px; height: 28px; border-radius: 4px; flex-shrink: 0;
            background: #22c55e;
        }
        .ftth-odc-line.offline { background: #ef4444; }
        .ftth-odc-head-name {
            flex: 1 1 auto; min-width: 0;
            font-size: 16px; font-weight: 700; color: #ffffff;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        body.ftth-light .ftth-odc-head-name { color: #1e293b; }
        .ftth-odc-badge {
            font-size: 10px; font-weight: 700; padding: 3px 9px; border-radius: 6px;
            background: rgba(30,58,100,0.7); color: #7eb8f0; text-transform: uppercase; letter-spacing: .05em;
            flex-shrink: 0;
        }
        body.ftth-light .ftth-odc-badge { background: rgba(30,58,100,0.12); color: #3b82f6; }
        .ftth-odc-close {
            flex-shrink: 0; width: 24px; height: 24px;
            display: flex; align-items: center; justify-content: center;
            border: none; background: #64748b; color: #fff;
            border-radius: 6px; cursor: pointer; font-size: 12px;
            transition: all .15s;
        }
        .ftth-odc-close:hover { background: #475569; color: #fff; }
        body.ftth-light .ftth-odc-close { background: #64748b; color: #fff; }
        body.ftth-light .ftth-odc-close:hover { background: #475569; color: #fff; }
        .ftth-odc-topo {
            display: flex; align-items: center; gap: 8px;
            background: rgba(8,16,30,0.7); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 8px; padding: 10px 12px; margin-top: 12px;
            color: #475569; font-size: 12px;
        }
        body.ftth-light .ftth-odc-topo { background: rgba(230,233,239,0.7); border-color: rgba(96,165,250,0.15); color: #94a3b8; }
        .ftth-odc-topo i { font-size: 13px; color: #475569; }
        body.ftth-light .ftth-odc-topo i { color: #94a3b8; }
        .ftth-odc-body { display: flex; flex-direction: column; gap: 0; }
        .ftth-odc-info-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 9px 0; font-size: 12px;
        }
        .ftth-odc-info-row + .ftth-odc-info-row { border-top: 1px solid rgba(255,255,255,0.05); }
        body.ftth-light .ftth-odc-info-row + .ftth-odc-info-row { border-top-color: rgba(0,0,0,0.06); }
        .ftth-odc-info-label { color: #94a3b8; white-space: nowrap; }
        body.ftth-light .ftth-odc-info-label { color: #64748b; }
        .ftth-odc-info-val { font-weight: 600; text-align: right; color: #e2e8f0; }
        body.ftth-light .ftth-odc-info-val { color: #1e293b; }
        .ftth-odc-info-val.green { color: #22c55e; }
        .ftth-odc-info-val.blue { color: #60a5fa; }
        .ftth-odc-info-val.cyan { color: #22d3ee; }
        .ftth-odc-onu-section { padding: 8px 0; border-top: 1px solid rgba(255,255,255,0.05); }
        body.ftth-light .ftth-odc-onu-section { border-top-color: rgba(0,0,0,0.06); }
        .ftth-odc-onu-title {
            font-size: 11px; font-weight: 600; color: #93c5fd;
            margin-bottom: 6px; letter-spacing: .02em;
        }
        body.ftth-light .ftth-odc-onu-title { color: #2563eb; }
        .ftth-odc-onu-item {
            display: flex; align-items: center; gap: 8px;
            padding: 6px 0; font-size: 12px;
        }
        .ftth-odc-onu-item i { font-size: 12px; color: #3b82f6; width: 16px; text-align: center; }
        .ftth-odc-onu-name { color: #e2e8f0; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        body.ftth-light .ftth-odc-onu-name { color: #1e293b; }
        .ftth-odc-onu-type { font-size: 10px; color: #64748b; background: rgba(96,165,250,0.1); padding: 1px 6px; border-radius: 4px; }
        body.ftth-light .ftth-odc-onu-type { color: #94a3b8; background: rgba(96,165,250,0.08); }
        .ftth-odc-onu-count { font-weight: 600; color: #93c5fd; white-space: nowrap; }
        .ftth-odc-distance {
            text-align: center; padding: 10px 0 6px; font-size: 15px; font-weight: 700; color: #64748b;
        }
        .ftth-odc-distance span { color: #e2e8f0; }
        body.ftth-light .ftth-odc-distance span { color: #1e293b; }
        .ftth-odc-btns { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 7px; margin-top: 0; }
         .ftth-odc-btn {
            display: flex; align-items: center; justify-content: center; gap: 5px;
            padding: 5px 0; border-radius: 7px; border: none;
            font-size: 11px; font-weight: 700; cursor: pointer; text-decoration: none;
            transition: all .15s; color: #fff;
        }
        .ftth-odc-btn i { font-size: 11px; }
        .ftth-odc-btn.blue { background: #2563eb; }
        .ftth-odc-btn.blue:hover { background: #3b82f6; }
        .ftth-odc-btn.green-dark { background: #16a34a; }
        .ftth-odc-btn.green-dark:hover { background: #22c55e; }
        .ftth-odc-btn.green-light { background: #16a34a; }
        .ftth-odc-btn.green-light:hover { background: #22c55e; }
        .ftth-odc-btn.cyan { background: #0891b2; }
        .ftth-odc-btn.cyan:hover { background: #06b6d4; }
        .ftth-odc-btn.red { background: #dc2626; }
        .ftth-odc-btn.red:hover { background: #ef4444; }
        .ftth-odc-btn.cyan { background: #0891b2; }
        .ftth-odc-btn.cyan:hover { background: #06b6d4; }
        .ftth-odc-btns-row2 { grid-template-columns: 1fr 1fr; margin-top: 7px; }
        .ftth-odc-btns-bottom { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; margin-top: 7px; }
         .ftth-odc-btn-lg {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            padding: 5px 0; border-radius: 7px;
            font-size: 12px; font-weight: 700; cursor: pointer; border: none;
            text-transform: uppercase; letter-spacing: .03em;
            transition: all .15s; color: #fff;
        }
        .ftth-odc-btn-lg i { font-size: 12px; }
         .ftth-odc-btn-lg.blue { background: #2563eb; }
        .ftth-odc-btn-lg.blue:hover { background: #3b82f6; }
        .ftth-odc-btn-lg.cyan { background: #0891b2; }
        .ftth-odc-btn-lg.cyan:hover { background: #06b6d4; }
        .ftth-odc-btn-lg.green-dark { background: #16a34a; }
        .ftth-odc-btn-lg.green-dark:hover { background: #22c55e; }
        .ftth-odc-btn-lg.red { background: #ef4444; }
        .ftth-odc-btn-lg.red:hover { background: #dc2626; }
        body.ftth-light .ftth-odc-btn.blue { background: #2563eb; color: #fff; }
        body.ftth-light .ftth-odc-btn.green-dark { background: #16a34a; color: #fff; }
        body.ftth-light .ftth-odc-btn.green-light { background: #16a34a; color: #fff; }
        body.ftth-light .ftth-odc-btn.red { background: #dc2626; color: #fff; }
        body.ftth-light .ftth-odc-btn.cyan { background: #0891b2; color: #fff; }
        body.ftth-light .ftth-odc-btn-lg.blue { background: #2563eb; color: #fff; }
        body.ftth-light .ftth-odc-btn-lg.cyan { background: #0891b2; color: #fff; }
        body.ftth-light .ftth-odc-btn-lg.green-dark { background: #16a34a; color: #fff; }
        body.ftth-light .ftth-odc-btn-lg.red { background: #ef4444; color: #fff; }

        /* ── Tabel ONU ── */
        .ftth-onu-table-card { width: max-content; min-width: 800px; max-width: calc(100vw - 32px); top: 66px; transform: translateX(-50%); max-height: calc(100vh - 130px); }
        .ftth-onu-table-head { flex-wrap: wrap; gap: 8px; }
        .ftth-onu-table-tools { display: flex; align-items: center; gap: 8px; margin-left: auto; flex-wrap: wrap; }
        .ftth-onu-table-tools .ftth-modal-close { margin-left: 0; }
        .ftth-onu-table-search {
            display: flex; align-items: center; gap: 6px;
            background: rgba(15,23,42,0.85);
            border: 1px solid rgba(51,65,85,0.8); border-radius: 8px;
            padding: 5px 10px;
        }
        .ftth-onu-table-search i { font-size: 12px; color: #64748b; }
        .ftth-onu-table-search input {
            background: none; border: none; outline: none;
            color: #e2e8f0; font-size: 12px; width: 170px;
        }
        .ftth-onu-table-search input::placeholder { color: #64748b; }
        .ftth-onu-table-btn {
            display: inline-flex; align-items: center; gap: 6px;
            border: none; border-radius: 8px; cursor: pointer;
            padding: 7px 12px; font-size: 12px; font-weight: 600; color: #fff;
        }
        .ftth-onu-table-btn i { font-size: 12px; }
        .ftth-btn-print { background: #2563eb; }
        .ftth-btn-print:hover { background: #3b82f6; }
        .ftth-btn-export { background: #16a34a; }
        .ftth-btn-export:hover { background: #22c55e; }
        .ftth-onu-dd.open .ftth-onu-table-btn { filter: brightness(1.1); }
        .ftth-onu-dd { position: relative; display: inline-block; }
        .ftth-onu-dd-menu {
            display: none; position: absolute; right: 0; top: calc(100% + 8px);
            z-index: 200;
            flex-direction: column; gap: 4px;
            min-width: 0; width: max-content; padding: 6px;
            background: #0f172a;
            border: 1px solid rgba(148,163,184,0.35);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .ftth-onu-dd.open .ftth-onu-dd-menu { display: flex; }
        .ftth-onu-dd-item {
            display: flex; align-items: center; gap: 10px;
            width: 100%; padding: 9px 10px;
            border-radius: 9px;
            border: 1px solid transparent;
            background: #152138;
            color: #fff;
            font-size: 11.5px; font-weight: 600;
            text-align: left; cursor: pointer; transition: all .2s;
        }
        .ftth-onu-dd-item i { font-size: 15px; width: 20px; text-align: center; }
        .ftth-onu-dd-item.ftth-dd-all { background: #16a34a; border-color: #15803d; }
        .ftth-onu-dd-item.ftth-dd-all:hover { background: #15803d; }
        .ftth-onu-dd-item.ftth-dd-ppp { background: #0284c7; border-color: #0369a1; }
        .ftth-onu-dd-item.ftth-dd-ppp:hover { background: #0369a1; }
        .ftth-onu-dd-item.ftth-dd-hotspot { background: #ea580c; border-color: #c2410c; }
        .ftth-onu-dd-item.ftth-dd-hotspot:hover { background: #c2410c; }
        .ftth-onu-dd-sep { height: 1px; background: rgba(100,116,139,0.25); margin: 4px 0; }
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
        .ftth-onu-table-footer {
            display: flex; justify-content: space-between; align-items: center;
            gap: 10px; flex-wrap: wrap; padding: 10px 2px 0;
        }
        .ftth-onu-table-info { font-size: 11.5px; color: #94a3b8; }
        .ftth-onu-table-pager { display: flex; align-items: center; gap: 8px; }
        .ftth-onu-table-page-btn {
            background: rgba(15,23,42,0.85);
            border: 1px solid rgba(51,65,85,0.8); border-radius: 6px;
            color: #e2e8f0; font-size: 11.5px; cursor: pointer; padding: 5px 10px;
        }
        .ftth-onu-table-page-btn:hover:not(:disabled) { background: rgba(59,130,246,0.25); border-color: rgba(59,130,246,0.5); }
        .ftth-onu-table-page-btn:disabled { opacity: .4; cursor: not-allowed; }
        .ftth-onu-table-page-num { font-size: 12px; font-weight: 700; color: #cbd5e1; min-width: 38px; text-align: center; }
        .ftth-bu-8:hover { background: rgba(217,70,239,0.38); }
        .ftth-file-hidden { display: none; }

        /* ── Responsive: tampilan rapi di Chrome HP (≤767px) ── */
        @media (max-width: 767px) {
            .ftth-toolbar {
                top: 8px;
                left: 8px;
                right: 8px;
                gap: 4px;
                padding: 2px;
            }
            .ftth-toolbar .ftth-btn {
                font-size: 0;
                padding: 0;
                width: 30px;
                height: 30px;
                justify-content: center;
                gap: 0;
            }
            .ftth-toolbar .ftth-btn .ftth-btn-ic { width: auto; }
            .ftth-toolbar .ftth-btn .ftth-btn-ic i { font-size: 12px; }
            .ftth-icon-btn { width: 28px; height: 28px; font-size: 12px; }
            .ftth-back-btn { width: 28px; height: 28px; }
            .ftth-ac-lock { margin-left: 0; }
            .ftth-ac-theme { margin-left: 0; }
            .ftth-search { width: 110px; padding: 3px 7px; }

            .ftth-fab-group { right: 14px; bottom: 14px; gap: 6px; }
            .ftth-fab { width: 46px; height: 46px; font-size: 20px; }
            .ftth-fab-trigger { width: 36px; height: 36px; font-size: 13px; }
            .ftth-style-btn { width: 38px; height: 38px; }

            .ftth-status { left: 10px; bottom: 10px; max-width: calc(100vw - 120px); font-size: 8px; }
            .ftth-status-item { padding: 3px 5px; }

            .ftth-modal-card {
                left: 50% !important;
                top: 50% !important;
                transform: translate(-50%, -50%) !important;
                width: calc(100vw - 24px) !important;
                max-width: calc(100vw - 24px) !important;
                max-height: calc(100vh - 20px) !important;
                border-radius: 12px;
            }

            .ftth-onu-table-tools { width: 100%; }
            .ftth-onu-table-search { flex: 1; }
            .ftth-onu-table-search input { width: 100%; }
            .ftth-device-cats { grid-template-columns: 1fr; }
            .ftth-browse-toolbar { flex-direction: column; align-items: stretch; }
            .ftth-browse-delall { text-align: center; }

            .ftth-detail-card {
                left: 0;
                top: 0;
                width: min(300px, calc(100vw - 20px));
                max-height: 46vh;
            }

            .ftth-measure-result {
                right: 10px;
                top: 84px;
                width: calc(100vw - 20px);
            }

            /* Kalkulator Redaman: wrap & card menyesuaikan layar HP */
            .ftth-calc-wrap { width: 100%; max-width: 440px; height: auto; }
            .ftth-modal-card.ftth-calc-card {
                position: relative !important;
                inset: auto !important;
                transform: none !important;
                width: 100% !important;
                margin: 0 auto;
                max-height: calc(100vh - 24px);
                overflow-y: auto;
            }
            .ftth-calc-card .ftth-modal-body { overflow: visible !important; }
            .ftth-calc-kuping {
                left: auto !important;
                right: 8px !important;
                top: 40px !important;
            }
            /* Tabel referensi jadi full-screen overlay di HP */
            .ftth-calc-ref {
                position: fixed !important;
                inset: 0 !important;
                width: auto !important;
                height: auto !important;
                max-height: none !important;
                border-radius: 0 !important;
                transform: translateX(100%) !important;
            }
            .ftth-calc-wrap.ref-open .ftth-calc-ref {
                transform: translateX(0) !important;
            }
        }

        /* ── Override: seluruh button/aksi warna solid tanpa transparansi ── */
        .ftth-btn, .ftth-back-btn, .ftth-icon-btn, .ftth-fab-trigger, .ftth-style-btn,
        .ftth-measure-item, .ftth-notif-item, .ftth-stepper button, .ftth-search-btn,
        .ftth-modal-card .ftth-modal-btn, .ftth-row-btn, .ftth-browse-close,
        .ftth-browse-delall, .ftth-browse-back, .ftth-device-row-del, .ftth-device-row-edit {
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
        }
        .ftth-btn { background: #0f172a; border-color: color-mix(in srgb, var(--ftth-accent) 55%, #0f172a); color: #e2e8f0; }
        .ftth-btn:hover { background: color-mix(in srgb, var(--ftth-accent) 45%, #0f172a); border-color: var(--ftth-accent); color: #fff; }
        .ftth-back-btn { background: #0f172a; border-color: rgba(96,165,250,0.5); color: #fff; }
        .ftth-back-btn:hover { background: #1e3a8a; border-color: #60a5fa; color: #fff; }
        .ftth-fab-trigger { background: #0f172a; color: #60a5fa; }
        .ftth-fab-trigger:hover, .ftth-fab-trigger.active { background: #2563eb; color: #fff; }
        .ftth-style-btn { background: #0f172a; color: #fff; }
        .ftth-style-btn:hover { background: #1d4ed8; color: #fff; }
        .ftth-style-btn.active { background: #2563eb; border-color: #60a5fa; color: #fff; }
        .ftth-measure-item { background: #0f172a; }
        .ftth-measure-item:hover { background: #15803d; border-color: #4ade80; color: #fff; }
        .ftth-measure-item[data-mode="otdr"]:hover { background: #0369a1; border-color: #38bdf8; color: #fff; }
        .ftth-notif-item { background: #0f172a; }
        .ftth-notif-item.wa:hover { background: #16a34a; border-color: #25d366; color: #fff; }
        .ftth-notif-item.tg:hover { background: #0284c7; border-color: #38bdf8; color: #fff; }
        .ftth-search-btn:hover { background: #b45309; color: #fff; }
        .ftth-stepper button { background: #7c2d12; color: #fdba74; }
        .ftth-stepper button:hover { background: #ea580c; color: #fff; }

        .ftth-modal-close:hover { background: #475569; color: #fff; }
        .ftth-modal-btn { background: #2563eb; border-color: #1d4ed8; color: #fff; }
        .ftth-modal-btn:hover { background: #1d4ed8; }
        .ftth-modal-btn.save { background: #16a34a; border-color: #15803d; }
        .ftth-modal-btn.save:hover { background: #15803d; }
        .ftth-modal-btn.syncall { background: #d97706; border-color: #b45309; }
        .ftth-modal-btn.syncall:hover { background: #b45309; }
        .ftth-row-btn { background: #2563eb; border-color: #1d4ed8; color: #fff; }
        .ftth-row-btn:hover { background: #1d4ed8; }
        .ftth-row-btn.sync { background: #16a34a; border-color: #15803d; }
        .ftth-row-btn.sync:hover { background: #15803d; }
        .ftth-row-btn.del { background: #dc2626; border-color: #b91c1c; }
        .ftth-row-btn.del:hover { background: #b91c1c; }
        .ftth-queue-item-add { background: #0d9488; border-color: #14b8a6; color: #fff; }
        .ftth-queue-item-add:hover { background: #14b8a6; color: #fff; }

        .ftth-bu-1 { background: #2563eb; border-color: #3b82f6; color: #fff; }
        .ftth-bu-1:hover { background: #1d4ed8; }
        .ftth-bu-2 { background: #0891b2; border-color: #06b6d4; color: #fff; }
        .ftth-bu-2:hover { background: #0e7490; }
        .ftth-bu-3 { background: #16a34a; border-color: #22c55e; color: #fff; }
        .ftth-bu-3:hover { background: #15803d; }
        .ftth-bu-4 { background: #0d9488; border-color: #14b8a6; color: #fff; }
        .ftth-bu-4:hover { background: #0f766e; }
        .ftth-bu-5 { background: #d97706; border-color: #f59e0b; color: #fff; }
        .ftth-bu-5:hover { background: #b45309; }
        .ftth-bu-6 { background: #dc2626; border-color: #ef4444; color: #fff; }
        .ftth-bu-6:hover { background: #b91c1c; }
        .ftth-bu-7 { background: #7c3aed; border-color: #8b5cf6; color: #fff; }
        .ftth-bu-7:hover { background: #6d28d9; }
        .ftth-bu-8 { background: #c026d3; border-color: #d946ef; color: #fff; }
        .ftth-bu-8:hover { background: #a21caf; }

        .ftth-pppoe-edit { background: #b45309; border-color: #d97706; color: #fff; }
        .ftth-pppoe-edit:hover, .ftth-pppoe-edit.active { background: #ea580c; color: #fff; box-shadow: 0 0 0 1px #f59e0b; }
        .ftth-btn-batal { background: #64748b; border-color: #475569; }
        .ftth-btn-batal:hover { background: #475569; }

        .ftth-device-row { background: #0f172a; border-color: #1e293b; }
        .ftth-device-row:hover { background: #1e293b; border-color: #3b82f6; }
        .ftth-device-row-del { background: #dc2626; border-color: #b91c1c; color: #fff; }
        .ftth-device-row-del:hover { background: #b91c1c; color: #fff; }
        .ftth-device-row-edit { background: #2563eb; border-color: #1d4ed8; color: #fff; }
        .ftth-device-row-edit:hover { background: #1d4ed8; color: #fff; }
        .ftth-device-row-status { background: #1e293b; border-color: #334155; color: #cbd5e1; }
        .ftth-device-row-status.st-online { background: #16a34a; border-color: #22c55e; color: #fff; }
        .ftth-device-row-status.st-offline { background: #dc2626; border-color: #ef4444; color: #fff; }
        .ftth-device-row-status.st-other { background: #d97706; border-color: #f59e0b; color: #fff; }
        .ftth-browse-delall { background: #dc2626; border-color: #b91c1c; color: #fff; }
        .ftth-browse-delall:hover { background: #b91c1c; color: #fff; }
        .ftth-browse-back { background: #1e293b; border-color: #334155; color: #e2e8f0; }
        .ftth-browse-back:hover { background: #2563eb; border-color: #1d4ed8; color: #fff; }
        .ftth-browse-close { background: #64748b; border-color: #475569; color: #fff; }
        .ftth-browse-close:hover { background: #475569; color: #fff; }
        .ftth-dev-cat { background: #0f172a; border-color: #1e293b; }
        .ftth-dev-cat:hover { background: #1e293b; border-color: #334155; }

        .ftth-detail-del { background: #dc2626; border-color: #b91c1c; color: #fff; }
        .ftth-detail-del:hover { background: #b91c1c; color: #fff; }
        .ftth-copy-btn { background: #2563eb; border-color: #1d4ed8; color: #fff; }
        .ftth-copy-btn:hover { background: #1d4ed8; color: #fff; }
        .ftth-cust-btn { background: #2563eb; border-color: #1d4ed8; color: #fff; }
        .ftth-cust-btn:hover { background: #1d4ed8; color: #fff; }
        .ftth-cust-btn.danger { background: #dc2626; border-color: #b91c1c; color: #fff; }
        .ftth-cust-btn.danger:hover { background: #b91c1c; color: #fff; }
        .ftth-cust-btn.whatsapp { background: #16a34a; border-color: #15803d; color: #fff; }
        .ftth-cust-btn.whatsapp:hover { background: #15803d; color: #fff; }

        .ftth-onu-table-page-btn { background: #1e293b; border-color: #334155; color: #e2e8f0; }
        .ftth-onu-table-page-btn:hover:not(:disabled) { background: #2563eb; border-color: #1d4ed8; color: #fff; }

        /* --- Mode Light (toggle .ftth-light di body): button fitur & card, palet lembut --- */
        body.ftth-light .ftth-btn { background: #f6f7f9; border-color: color-mix(in srgb, var(--ftth-accent) 40%, #f6f7f9); color: #334155; box-shadow: 0 6px 20px rgba(15,23,42,0.08); }
        body.ftth-light .ftth-btn:hover { background: color-mix(in srgb, var(--ftth-accent) 15%, #f6f7f9); border-color: color-mix(in srgb, var(--ftth-accent) 70%, #f6f7f9); color: #1e293b; }
        body.ftth-light .ftth-btn:hover .ftth-btn-ic i { color: var(--ftth-accent); }
        body.ftth-light .ftth-btn.ftth-syncing .ftth-btn-ic::after { border-top-color: var(--ftth-accent); border-right-color: var(--ftth-accent); }
        body.ftth-light .ftth-back-btn { background: #f6f7f9; border-color: rgba(59,130,246,0.4); color: #334155; box-shadow: 0 6px 20px rgba(15,23,42,0.08); }
        body.ftth-light .ftth-back-btn:hover { background: #e9edf3; border-color: rgba(59,130,246,0.6); color: #1e293b; }
        body.ftth-light .ftth-back-btn:hover i { color: #2563eb; }
        body.ftth-light .ftth-icon-btn { background: #f6f7f9; border-color: color-mix(in srgb, var(--ftth-accent) 40%, #f6f7f9); color: #334155; box-shadow: 0 6px 20px rgba(15,23,42,0.08); }
        body.ftth-light .ftth-icon-btn:hover,
        body.ftth-light .ftth-icon-btn.active { background: color-mix(in srgb, var(--ftth-accent) 15%, #f6f7f9); border-color: color-mix(in srgb, var(--ftth-accent) 70%, #f6f7f9); color: #1e293b; }
        body.ftth-light .ftth-icon-btn:hover i,
        body.ftth-light .ftth-icon-btn.active i { color: var(--ftth-accent); }
        body.ftth-light .ftth-search { background: #f6f7f9; border-color: color-mix(in srgb, var(--ftth-accent) 40%, #f6f7f9); }
        body.ftth-light .ftth-search:focus-within { background: #eef2f7; border-color: rgba(96,165,250,0.6); }
        body.ftth-light .ftth-search input { color: #334155; }
        body.ftth-light .ftth-search input::placeholder { color: #94a3b8; }
        body.ftth-light .ftth-search-btn { color: #b45309; }
        body.ftth-light .ftth-search-btn:hover { background: #d97706; color: #fff; }
        body.ftth-light .ftth-style-btn { background: #f6f7f9; color: #334155; border-color: rgba(100,116,139,0.35); }
        body.ftth-light .ftth-style-btn:hover { background: #e9edf3; color: #1e293b; }
        body.ftth-light .ftth-style-btn.active { background: #2563eb; border-color: #1d4ed8; color: #fff; }
        body.ftth-light .ftth-fab-trigger { background: #f6f7f9; color: #2563eb; border-color: rgba(59,130,246,0.4); }
        body.ftth-light .ftth-fab-trigger:hover,
        body.ftth-light .ftth-fab-trigger.active { background: #2563eb; color: #fff; }

        body.ftth-light .ftth-measure-menu,
        body.ftth-light .ftth-notif-menu,
        body.ftth-light .ftth-search-suggest { background: #f6f7f9; box-shadow: 0 10px 30px rgba(15,23,42,0.14); }
        body.ftth-light .ftth-measure-item { background: #f6f7f9; color: #334155; }
        body.ftth-light .ftth-measure-item small { color: #64748b; }
        body.ftth-light .ftth-measure-item:hover { background: #16a34a; color: #fff; }
        body.ftth-light .ftth-measure-item:hover small { color: #dcfce7; }
        body.ftth-light .ftth-measure-item[data-mode="otdr"]:hover { background: #0369a1; color: #fff; }
        body.ftth-light .ftth-notif-item { background: #f6f7f9; color: #334155; }
        body.ftth-light .ftth-notif-item.wa:hover { background: #16a34a; color: #fff; }
        body.ftth-light .ftth-notif-item.tg:hover { background: #0284c7; color: #fff; }
        body.ftth-light .ftth-search-suggest .ftth-sug-item { color: #334155; }
        body.ftth-light .ftth-search-suggest .ftth-sug-item:hover,
        body.ftth-light .ftth-search-suggest .ftth-sug-item.active { background: #e9edf3; color: #1e293b; }

        body.ftth-light .ftth-modal-card { background: #f6f7f9; border-color: rgba(96,165,250,0.3); box-shadow: 0 20px 60px rgba(15,23,42,0.16); color: #334155; }
        body.ftth-light .ftth-modal-head { border-bottom-color: rgba(96,165,250,0.22); color: #1e293b; }
        body.ftth-light .ftth-modal-close { background: #64748b; color: #fff; }
        body.ftth-light .ftth-modal-close:hover { background: #475569; color: #fff; }
        body.ftth-light .ftth-mt-status { background: #e6e9ef; color: #475569; }
        body.ftth-light .ftth-mt-status.ok { background: #dcf5e3; color: #15803d; }
        body.ftth-light .ftth-mt-status.fail { background: #fbe3e3; color: #b91c1c; }
        body.ftth-light .ftth-mt-status.info { background: #dbeafe; color: #1d4ed8; }
        body.ftth-light .ftth-router-row { background: #eef1f5; border-color: rgba(96,165,250,0.22); }
        body.ftth-light .ftth-router-version { color: #64748b; }
        body.ftth-light .ftth-router-empty { color: #8b5cf6; }

        body.ftth-light .ftth-form label { color: #475569; }
        body.ftth-light .ftth-form input,
        body.ftth-light .ftth-form select,
        body.ftth-light .ftth-df > input,
        body.ftth-light .ftth-df > select,
        body.ftth-light .ftth-queue-toolbar input,
        body.ftth-light .ftth-vis-select,
        body.ftth-light .ftth-calc-card input[type="number"],
        body.ftth-light .ftth-calc-card select { background: #eef1f5; border-color: rgba(100,116,139,0.32); color: #334155; }
        body.ftth-light .ftth-form select option,
        body.ftth-light .ftth-df > select option,
        body.ftth-light .ftth-vis-select option,
        body.ftth-light .ftth-calc-card select option { background: #f6f7f9; color: #334155; }
        body.ftth-light .ftth-core-chk-box { background: #eef1f5; border-color: rgba(59,130,246,0.45); }
        body.ftth-light .ftth-core-chk-box.checked { background: #2563eb; border-color: #1d4ed8; }

        body.ftth-light .ftth-calc-label { color: #475569; }
        body.ftth-light .ftth-calc-hint,
        body.ftth-light .ftth-calc-note { color: #64748b; border-top-color: rgba(251,146,60,0.2); background: #fdf3e7; }
        body.ftth-light .ftth-calc-result { background: #fdf3e7; border-color: rgba(251,146,60,0.3); }
        body.ftth-light .ftth-calc-ont-power { background: #faf0e2; border-color: rgba(251,146,60,0.22); }
        body.ftth-light .ftth-calc-ont-power .ftth-calc-ont-label { color: #475569; }
        body.ftth-light .ftth-calc-ont-power b { color: #1e293b; }
        body.ftth-light .ftth-calc-ont-power.status-optimal { background: #ecfdf5; border-color: rgba(34,197,94,0.35); }
        body.ftth-light .ftth-calc-ont-power.status-optimal b { color: #15803d; }
        body.ftth-light .ftth-calc-ont-power.status-strong { background: #fefce8; border-color: rgba(250,204,21,0.35); }
        body.ftth-light .ftth-calc-ont-power.status-strong b { color: #a16207; }
        body.ftth-light .ftth-calc-ont-power.status-warn { background: #fffbeb; border-color: rgba(251,191,36,0.35); }
        body.ftth-light .ftth-calc-ont-power.status-warn b { color: #b45309; }
        body.ftth-light .ftth-calc-ont-power.status-bad { background: #fef2f2; border-color: rgba(248,113,113,0.35); }
        body.ftth-light .ftth-calc-ont-power.status-bad b { color: #dc2626; }
        body.ftth-light .ftth-calc-status-inner.status-optimal { background: rgba(34,197,94,0.10); border-color: rgba(34,197,94,0.35); color: #15803d; }
        body.ftth-light .ftth-calc-status-inner.status-strong { background: rgba(250,204,21,0.10); border-color: rgba(250,204,21,0.35); color: #a16207; }
        body.ftth-light .ftth-calc-status-inner.status-warn { background: rgba(251,191,36,0.10); border-color: rgba(251,191,36,0.35); color: #b45309; }
        body.ftth-light .ftth-calc-status-inner.status-bad { background: rgba(248,113,113,0.10); border-color: rgba(248,113,113,0.35); color: #dc2626; }
        body.ftth-light .ftth-calc-detail-row { color: #475569; }
        body.ftth-light .ftth-calc-detail-row b { color: #334155; }
        body.ftth-light .ftth-calc-detail-sm b { color: #64748b; }
        body.ftth-light .ftth-calc-detail-total { background: rgba(251,146,60,0.08) !important; border-color: rgba(251,146,60,0.3) !important; }
        body.ftth-light .ftth-calc-detail-total b { color: #c2410c; }
        body.ftth-light .ftth-calc-detail-total.status-optimal b { color: #15803d; }
        body.ftth-light .ftth-calc-detail-total.status-strong b { color: #a16207; }
        body.ftth-light .ftth-calc-detail-total.status-warn b { color: #b45309; }
        body.ftth-light .ftth-calc-detail-total.status-bad b { color: #dc2626; }
        body.ftth-light .ftth-calc-detail-odp { background: rgba(96,165,250,0.08) !important; border-color: rgba(96,165,250,0.3) !important; }
        body.ftth-light .ftth-calc-detail-odp b { color: #2563eb; }
        body.ftth-light .ftth-calc-card .ftth-modal-head > i { color: #fb923c; }
        body.ftth-light .ftth-calc-mode { background: #fdf3e7; border-color: rgba(251,146,60,0.22); }
        body.ftth-light .ftth-calc-mode-btn { color: #94a3b8; }
        body.ftth-light .ftth-calc-mode-btn.active { box-shadow: none; }
        body.ftth-light .ftth-calc-ref { background: #f6f7f9; border-color: rgba(251,146,60,0.35); }
        body.ftth-light .ftth-calc-ref-title { border-bottom-color: rgba(251,146,60,0.2); }
        body.ftth-light .ftth-calc-ref-title > i { color: #ea580c; }
        body.ftth-light .ftth-calc-ref-label { color: #334155; }
        body.ftth-light .ftth-calc-ref-label > i { color: #ea580c; }
        body.ftth-light .ftth-calc-ref-tbl th { background: rgba(251,146,60,0.1); color: #c2410c; border-bottom-color: rgba(251,146,60,0.2); }
        body.ftth-light .ftth-calc-ref-tbl td { color: #334155; border-bottom-color: rgba(251,146,60,0.1); }
        body.ftth-light .ftth-calc-ref-tbl tr:hover td { background: rgba(251,146,60,0.06); }
        body.ftth-light .ftth-calc-ref-comp { background: rgba(251,146,60,0.05); border-color: rgba(251,146,60,0.18); }
        body.ftth-light .ftth-calc-ref-comp-row { color: #475569; }
        body.ftth-light .ftth-calc-ref-close { background: #64748b; border-top-color: #475569; color: #fff; }
        body.ftth-light .ftth-calc-ref-close:hover { background: #475569; color: #fff; }
        body.ftth-light .ftth-calc-kuping {
            background: rgba(241,245,249,0.97);
            border-color: rgba(251,146,60,0.35);
            color: #ea580c;
        }
        body.ftth-light .ftth-calc-kuping:hover {
            background: rgba(226,232,240,0.97);
            color: #c2410c;
        }

        body.ftth-light .ftth-queue-toolbar { border-bottom-color: rgba(96,165,250,0.22); }
        body.ftth-light .ftth-queue-item { background: #eef1f5; border-color: #dbe1e8; }
        body.ftth-light .ftth-queue-item:hover { background: #e9edf3; border-color: rgba(96,165,250,0.45); }
        body.ftth-light .ftth-queue-item-name { color: #1d4ed8; }
        body.ftth-light .ftth-queue-item-ip { color: #64748b; }
        body.ftth-light .ftth-queue-list-wrap { scrollbar-color: auto; }
        body.ftth-light .ftth-dropdown-menu { background: #fff; border-color: rgba(148,163,184,0.35); box-shadow: 0 16px 40px rgba(15,23,42,0.16); }
        body.ftth-light .ftth-dropdown-item { background: #f6f7f9; color: #334155; }
        body.ftth-light .ftth-dropdown-item.ftth-dd-pppoe { border-color: rgba(8,145,178,0.3); }
        body.ftth-light .ftth-dropdown-item.ftth-dd-pppoe:hover { background: #22d3ee; border-color: #0e7490; color: #063b46; }
        body.ftth-light .ftth-dropdown-item.ftth-dd-hotspot { border-color: rgba(234,88,12,0.3); }
        body.ftth-light .ftth-dropdown-item.ftth-dd-hotspot:hover { background: #fb923c; border-color: #c2410c; color: #7c2d12; }

        body.ftth-light .ftth-bs-1 { background: #e9eef7; border-color: rgba(59,130,246,0.4); }
        body.ftth-light .ftth-bs-2 { background: #e9f6ec; border-color: rgba(34,197,94,0.4); }
        body.ftth-light .ftth-bs-3 { background: #f8eee1; border-color: rgba(249,115,22,0.4); }
        body.ftth-light .ftth-bs-4 { background: #f1ebf8; border-color: rgba(168,85,247,0.4); }
        body.ftth-light .ftth-bs-tag { background: #fdf0cc; color: #92400e; }

        body.ftth-light .ftth-device-status { background: #e6e9ef; border-color: #dbe1e8; color: #475569; }
        body.ftth-light .ftth-device-row { background: #eef1f5; border-color: #dbe1e8; }
        body.ftth-light .ftth-device-row:hover { background: #e9edf3; border-color: rgba(96,165,250,0.45); }
        body.ftth-light .ftth-device-row-name { color: #1e293b; }
        body.ftth-light .ftth-device-row-sub { color: #64748b; }
        body.ftth-light .ftth-device-row-status { background: #e6e9ef; border-color: #cbd5e1; color: #475569; }
        body.ftth-light .ftth-device-row-status.st-online { background: #16a34a; border-color: #22c55e; color: #fff; }
        body.ftth-light .ftth-device-row-status.st-offline { background: #dc2626; border-color: #ef4444; color: #fff; }
        body.ftth-light .ftth-device-row-status.st-other { background: #d97706; border-color: #f59e0b; color: #fff; }
        body.ftth-light .ftth-device-empty { color: #8b5cf6; }
        body.ftth-light .ftth-dev-cat { background: #eef1f5; border-color: #dbe1e8; }
        body.ftth-light .ftth-dev-cat:hover { background: #e9edf3; border-color: #cbd5e1; }
        body.ftth-light .ftth-dev-cat-ic { background: color-mix(in srgb, var(--c) 13%, #f6f7f9); }
        body.ftth-light .ftth-dev-cat-name { color: #1e293b; }
        body.ftth-light .ftth-dev-cat-jelajahi { color: #64748b; }
        body.ftth-light .ftth-browse-search { background: #e6e9ef; border-color: #dbe1e8; }
        body.ftth-light .ftth-browse-search input { color: #334155; }
        body.ftth-light .ftth-browse-search input::placeholder { color: #94a3b8; }
        body.ftth-light .ftth-browse-back { background: #e6e9ef; border-color: #dbe1e8; color: #334155; }
        body.ftth-light .ftth-browse-back:hover { background: #2563eb; border-color: #1d4ed8; color: #fff; }

        body.ftth-light .ftth-onu-table-search { background: #e6e9ef; border-color: #dbe1e8; }
        body.ftth-light .ftth-onu-table-search input { color: #334155; }
        body.ftth-light .ftth-onu-table-search input::placeholder { color: #94a3b8; }
        body.ftth-light .ftth-onu-table-wrap { border-color: #dbe1e8; }
        body.ftth-light .ftth-onu-table th { background: #e6e9ef; color: #475569; border-bottom-color: #dbe1e8; }
        body.ftth-light .ftth-onu-table td { color: #334155; border-bottom-color: #e6e9ef; }
        body.ftth-light .ftth-onu-table tbody tr:hover td { background: #e9edf3; }
        body.ftth-light .ftth-onu-table-info { color: #64748b; }
        body.ftth-light .ftth-onu-table-page-btn { background: #e6e9ef; border-color: #dbe1e8; color: #334155; }
        body.ftth-light .ftth-onu-table-page-btn:hover:not(:disabled) { background: #2563eb; border-color: #1d4ed8; color: #fff; }
        body.ftth-light .ftth-onu-table-page-num { color: #475569; }
        body.ftth-light .ftth-onu-dd-menu { background: #fff; border-color: rgba(148,163,184,0.35); box-shadow: 0 10px 30px rgba(15,23,42,0.14); }
        body.ftth-light .ftth-onu-dd-item { color: #fff; }
        body.ftth-light .ftth-onu-dd-item.ftth-dd-all { background: #16a34a; border-color: #15803d; }
        body.ftth-light .ftth-onu-dd-item.ftth-dd-ppp { background: #0284c7; border-color: #0369a1; }
        body.ftth-light .ftth-onu-dd-item.ftth-dd-hotspot { background: #ea580c; border-color: #c2410c; }
        body.ftth-light .ftth-onu-dd-item.ftth-dd-all:hover { background: #15803d; }
        body.ftth-light .ftth-onu-dd-item.ftth-dd-ppp:hover { background: #0369a1; }
        body.ftth-light .ftth-onu-dd-item.ftth-dd-hotspot:hover { background: #c2410c; }
        body.ftth-light .ftth-onu-dd-sep { background: #e2e8f0; }

        body.ftth-light .ftth-detail-card { background: #f6f7f9; border-color: rgba(96,165,250,0.3); color: #334155; box-shadow: 0 16px 44px rgba(15,23,42,0.14); }
        body.ftth-light .ftth-detail-head { border-bottom-color: rgba(96,165,250,0.22); }
        body.ftth-light .ftth-detail-grip { color: #94a3b8; }
        body.ftth-light .ftth-detail-name { color: #1e293b; }
        body.ftth-light .ftth-detail-row { color: #334155; }
        body.ftth-light .ftth-detail-attr b { color: #64748b; }
        body.ftth-light .ftth-detail-attr span { color: #1e293b; }
        body.ftth-light .ftth-detail-notes { color: #b45309; }
        body.ftth-light .ftth-log-row { background: #eef1f5; border-color: #dbe1e8; color: #334155; }
        body.ftth-light .ftth-log-row.ok { border-color: rgba(34,197,94,0.45); color: #15803d; }
        body.ftth-light .ftth-log-row.err { border-color: rgba(239,68,68,0.45); color: #b91c1c; }
        body.ftth-light .ftth-log-row.info { border-color: rgba(96,165,250,0.45); color: #1d4ed8; }
        body.ftth-light .ftth-detail-live-head { color: #2563eb; }

        body.ftth-light .ftth-measure-result { background: #f6f7f9; border-color: rgba(74,222,128,0.35); color: #334155; box-shadow: 0 10px 30px rgba(15,23,42,0.14); }
        body.ftth-light .ftth-measure-result-title { color: #1e293b; }
        body.ftth-light .ftth-measure-otdr .ftth-measure-result-title { color: #0369a1; }
        body.ftth-light .ftth-measure-x { background: #64748b; color: #fff; }
        body.ftth-light .ftth-measure-x:hover { background: #475569; color: #fff; }
        body.ftth-light .ftth-measure-result-hint { color: #64748b; }
        body.ftth-light .fm-row b { color: #15803d; }
        body.ftth-light .ftth-measure-otdr .fm-row b { color: #0369a1; }
        body.ftth-light .ftth-measure-act { background: #64748b; border-color: #475569; color: #fff; }
        body.ftth-light .ftth-measure-act:hover { background: #475569; color: #fff; }
        body.ftth-light .ftth-measure-act.ftth-measure-otdr-act { background: #64748b; border-color: #475569; color: #fff; }
        body.ftth-light .ftth-measure-act.ftth-measure-otdr-act:hover { background: #475569; color: #fff; }

        body.ftth-light .ftth-vis-section { color: #0e7490; }
        body.ftth-light .ftth-vis-check { color: #334155; }
        body.ftth-light .ftth-vis-check small { color: #64748b; }
        body.ftth-light .ftth-vis-note { color: #64748b; }

        body.ftth-light .ftth-status { background: #f6f7f9; border-color: rgba(96,165,250,0.26); color: #334155; box-shadow: 0 6px 20px rgba(15,23,42,0.08); }
        body.ftth-light .ftth-copyright { background: #f6f7f9; border-color: rgba(96,165,250,0.26); color: #64748b; box-shadow: 0 6px 20px rgba(15,23,42,0.08); }
        body.ftth-light .ftth-toast { background: #f6f7f9; border-color: rgba(96,165,250,0.35); color: #334155; }

        /* Mode light: semua scrollbar kembali ke tampilan klasik (native) */
        html:has(body.ftth-light) { scrollbar-width: auto; scrollbar-color: auto; }
        body.ftth-light ::-webkit-scrollbar { width: 16px; height: 16px; }
        body.ftth-light ::-webkit-scrollbar-track { background: #f1f1f1; }
        body.ftth-light ::-webkit-scrollbar-thumb { background: #c1c1c1; }
        body.ftth-light ::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }
        body.ftth-light ::-webkit-scrollbar-corner { background: #f1f1f1; }

        /* ── Card Wireless ONU (rebuild) ── */
        /* ── Card ONU pelanggan (wireless / hotspot) — desain dark navy ── */
        .ftth-onu-card { display: flex; flex-direction: column; gap: 7px; padding: 0; font-family: inherit; }
        /* Header ONU: garis hijau + nama (pojok kiri) + badge tipe ONU (pojok kanan) + X */
        .ftth-detail-head--onu { padding: 9px 10px; border-bottom-color: rgba(96,165,250,0.18); }
        .ftth-detail-head--onu .ftth-detail-grip { display: none; }
        .ftth-detail-head--onu::before {
            content: ''; order: 0; flex: 0 0 auto;
            width: 4px; height: 22px; border-radius: 4px;
            background: #22c55e; box-shadow: 0 0 8px rgba(34,197,94,0.6);
        }
        .ftth-detail-head--onu #ftthDetailName {
            order: 1; flex: 1 1 auto; min-width: 0;
            font-size: 13px; font-weight: 800; color: #f1f5f9;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .ftth-detail-head--onu #ftthDetailBadge {
            order: 2; flex: 0 0 auto;
            font-size: 10px; font-weight: 800; letter-spacing: .05em;
            padding: 3px 9px; border-radius: 6px; text-transform: uppercase;
            background: rgba(59,130,246,0.2); color: #93c5fd; border: 1px solid rgba(59,130,246,0.45);
        }
        .ftth-detail-head--onu .ftth-modal-close { order: 3; }
        /* IP section */
        .ftth-onu-ipbox { display: flex; align-items: center; gap: 8px; background: rgba(15,23,42,0.6); border: 1px solid rgba(96,165,250,0.2); border-radius: 9px; padding: 7px 9px; }
        .ftth-onu-ipbox > i.fa-network-wired { color: #60a5fa; font-size: 13px; }
        .ftth-onu-ipbox #ftthOnuIp { flex: 1 1 auto; min-width: 0; font-size: 12.5px; font-weight: 700; color: #e2e8f0; font-variant-numeric: tabular-nums; }
         .ftth-onu-port { display: none; }
         .ftth-onu-type { display: none; }
         .ftth-onu-globe { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 18px; border-radius: 5px; border: 1px solid rgba(34,211,238,0.4); background: rgba(34,211,238,0.12); color: #22d3ee; font-size: 11px; text-decoration: none; flex: 0 0 auto; }
         .ftth-onu-globe:hover { background: rgba(34,211,238,0.25); }
         .ftth-onu-ping { display: inline-flex; align-items: center; justify-content: center; gap: 3px; padding: 0 6px; height: 18px; border-radius: 5px; border: 1px solid rgba(245,158,11,0.4); background: rgba(245,158,11,0.12); color: #f59e0b; font-size: 10px; font-weight: 700; cursor: pointer; flex: 0 0 auto; }
         .ftth-onu-ping:hover { background: rgba(245,158,11,0.25); }
        /* Status row */
        .ftth-onu-statusrow { display: flex; align-items: center; justify-content: space-between; font-size: 11.5px; color: #94a3b8; padding: 0 2px; }
        .ftth-onu-statusrow > span { letter-spacing: .03em; }
        .ftth-onu-statusrow b { font-weight: 800; font-variant-numeric: tabular-nums; }
        .ftth-onu-statusrow b.online { color: #4ade80; }
        .ftth-onu-statusrow b.offline { color: #f87171; }
        .ftth-onu-statusrow b.other { color: #cbd5e1; }
        /* Boxes */
        .ftth-onu-box { background: rgba(15,23,42,0.55); border: 1px solid rgba(96,165,250,0.18); border-radius: 11px; padding: 9px 10px; }
        .ftth-onu-acs-head { display: flex; align-items: center; justify-content: space-between; gap: 7px; margin-bottom: 8px; }
        .ftth-onu-acs-title { font-size: 10px; font-weight: 600; color: #a78bfa; letter-spacing: .03em; }
        .ftth-onu-acs-title.off { color: #94a3b8; }
         .ftth-onu-acs-loading { display: flex; align-items: center; gap: 7px; font-size: 11.5px; color: #a78bfa; padding: 0; margin-right: auto; }
        .ftth-onu-acs-loading i { color: #a78bfa; }
        .ftth-onu-uptime { font-size: 10.5px; color: #94a3b8; font-weight: 600; font-variant-numeric: tabular-nums; }
        .ftth-onu-atten { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .ftth-onu-atten-item { background: rgba(7,17,31,0.5); border: 1px solid rgba(148,163,184,0.12); border-radius: 9px; padding: 7px 9px; display: flex; flex-direction: column; gap: 2px; }
        .ftth-onu-atten-item span { font-size: 9.5px; color: #94a3b8; letter-spacing: .02em; }
        .ftth-onu-atten-item b { font-size: 13.5px; font-weight: 800; font-variant-numeric: tabular-nums; }
        .ftth-onu-atten-good b { color: #4ade80; }
        .ftth-onu-atten-warn b { color: #fbbf24; }
        .ftth-onu-atten-bad b { color: #f87171; }
        .ftth-onu-wifi-row { display: flex; align-items: center; gap: 8px; font-size: 11.5px; color: #cbd5e1; margin-top: 8px; }
        .ftth-onu-wifi-row > span { min-width: 72px; color: #94a3b8; }
        .ftth-onu-wifi-row > b { flex: 1; word-break: break-all; font-weight: 700; color: #e2e8f0; }
        .ftth-onu-eye { cursor: pointer; color: #60a5fa; }
        .ftth-onu-clients { display: flex; flex-direction: column; gap: 2px; font-size: 11.5px; color: #cbd5e1; margin-top: 6px; }
        .ftth-onu-clients b { color: #e2e8f0; font-weight: 700; }
         .ftth-onu-acs-actions { display: flex; justify-content: space-between; gap: 7px; margin-top: 7px; }
         .ftth-onu-btn-ganti, .ftth-onu-btn-reboot {
             display: flex; align-items: center; justify-content: center; gap: 5px;
             flex: 1; padding: 5px 0; border-radius: 7px; border: none;
             font-size: 11px; font-weight: 700; cursor: pointer; color: #fff;
             transition: all .15s;
         }
         .ftth-onu-btn-ganti { background: #16a34a; }
         .ftth-onu-btn-ganti:hover { background: #22c55e; }
         .ftth-onu-btn-reboot { background: #dc2626; }
         .ftth-onu-btn-reboot:hover { background: #ef4444; }
        .ftth-onu-ganti { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .ftth-onu-ganti input { flex: 1 1 100%; padding: 7px 9px; border-radius: 8px; border: 1px solid rgba(96,165,250,0.3); background: rgba(7,17,31,0.7); color: #fff; font-size: 12px; }
        .ftth-onu-ganti button { padding: 7px 12px; border-radius: 8px; border: 1px solid rgba(34,197,94,0.5); background: rgba(34,197,94,0.15); color: #86efac; cursor: pointer; font-size: 12px; font-weight: 700; }
         /* Live traffic */
         .ftth-onu-traffic { padding: 7px 10px 8px; }
         .ftth-onu-traffic-head { display: flex; align-items: center; gap: 10px; font-size: 10px; font-weight: 800; color: #cbd5e1; margin-bottom: 4px; flex-wrap: nowrap; white-space: nowrap; }
         .ftth-onu-traffic-head > span.ftth-onu-traffic-title { color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; font-weight: 800; font-size: 9px; margin-right: auto; }
        .ftth-onu-tx, .ftth-onu-rx { display: inline-flex; align-items: center; gap: 4px; font-weight: 700; font-variant-numeric: tabular-nums; font-size: 10px; }
        .ftth-onu-tx { color: #60a5fa; }
        .ftth-onu-rx { color: #4ade80; }
        .ftth-onu-tx i, .ftth-onu-rx i { width: 6px; height: 6px; border-radius: 50%; display: inline-block; flex: 0 0 auto; }
        .ftth-onu-tx i { background: #3b82f6; }
        .ftth-onu-rx i { background: #22c55e; }
        .ftth-onu-tx b, .ftth-onu-rx b { font-size: 10px; font-weight: 700; min-width: 58px; text-align: right; display: inline-block; }
         .ftth-onu-traffic-chart { position: relative; width: 100%; height: 72px; }
         .ftth-onu-traffic canvas { width: 100% !important; height: 100% !important; display: block; }
         .ftth-onu-traffic-status { font-size: 10px; font-weight: 600; text-align: center; margin-top: 4px; min-height: 13px; letter-spacing: .02em; color: #94a3b8; }
         .ftth-onu-traffic-status.live { color: #4ade80; }
         .ftth-onu-traffic-status.live::before { content: ''; display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #4ade80; margin-right: 5px; vertical-align: middle; animation: ftthBlink 1s ease-in-out infinite; }
          .ftth-onu-traffic-status.off { color: #f87171; }
          .ftth-onu-traffic-status.ftth-status-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; text-align: left; }
          .ftth-onu-traffic-status.ftth-status-row::before { display: none; }
          .ftth-onu-clients-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 9px; font-weight: 800; color: #38bdf8; background: rgba(56,189,248,0.12); border: 1px solid rgba(56,189,248,0.35); border-radius: 6px; padding: 1px 6px; }
          .ftth-onu-clients-badge i { font-size: 10px; }
          .ftth-onu-clients-badge b { color: #e0f2fe; }
          .ftth-onu-hs-clients { margin-top: 8px; display: flex; flex-direction: column; gap: 5px; }
          .ftth-onu-hs-client { display: flex; align-items: center; gap: 7px; font-size: 10px; background: rgba(15,23,42,0.45); border: 1px solid rgba(56,189,248,0.18); border-radius: 7px; padding: 4px 8px; }
          .ftth-onu-hs-client .hs-dot { width: 7px; height: 7px; border-radius: 50%; background: #38bdf8; flex-shrink: 0; }
          .ftth-onu-hs-client.unmapped .hs-dot { background: #f59e0b; }
          .ftth-onu-hs-client .hs-onu { color: #e2e8f0; font-weight: 700; }
          .ftth-onu-hs-client .hs-meta { color: #94a3b8; margin-left: auto; font-size: 9px; }
          .ftth-onu-hs-empty { font-size: 10px; color: #94a3b8; text-align: center; padding: 2px 0; }
          @keyframes ftthBlink { 0%, 100% { opacity: 1; } 50% { opacity: 0.2; } }
        /* Footer */
        .ftth-onu-footer { text-align: center; font-size: 10px; font-weight: 700; color: #e2e8f0; font-variant-numeric: tabular-nums; padding: 5px 0 2px; margin-top: 0; border-top: 1px solid rgba(96,165,250,0.18); }
    </style>
</head>
<body>
    <div id="ftth-map"></div>

    <div id="ftthMapLoader" class="ftth-map-loader" hidden>
        <div class="ftth-a-loader"><svg viewBox="-2 -2 58 52"><path class="ftth-a-chevron" d="M6 38 L26 8 L46 38"/><g class="ftth-a-check-group"><path class="ftth-a-check" d="M22 26 C10 30 16 44 28 34 C36 26 42 20 44 19"/><circle class="ftth-a-tip" cx="50" cy="17" r="2.5"/></g></svg></div>
        <div class="ftth-map-loader-msg">Memuat data perangkat...</div>
    </div>

    <div class="ftth-toolbar">
        <a href="{{ route('noc.dashboard') }}" class="ftth-back-btn ftth-ac-back" title="Kembali ke NOC Dashboard" data-i18n="common.back_noc">
            <i class="fa-solid fa-arrow-left"></i>
        </a>

        <button type="button" class="ftth-btn ftth-ac-mikrotik" id="ftth-sync-mk-btn" data-feature="sync-mikrotik" title="Sync Mikrotik" data-i18n="btn.sync_mikrotik" onclick="ftthOpenMikrotik()">
            <span class="ftth-btn-ic"><i class="fa-solid fa-server"></i></span>
            Sync Mikrotik
        </button>

        <button type="button" class="ftth-btn ftth-ac-olt" id="ftth-sync-olt-btn" data-feature="sync-olt" title="Sync OLT" data-i18n="btn.sync_olt" onclick="ftthOpenOlt()">
            <span class="ftth-btn-ic"><i class="fa-solid fa-tower-broadcast"></i></span>
            Sync OLT
        </button>

        <button type="button" class="ftth-btn ftth-ac-genieacs" id="ftth-sync-acs-btn" data-feature="sync-genieacs" title="Sync GenieACS" data-i18n="btn.sync_genieacs" onclick="ftthOpenGenieacs()">
            <span class="ftth-btn-ic"><i class="fa-solid fa-satellite-dish"></i></span>
            Sync GenieACS
        </button>

        <button type="button" class="ftth-btn ftth-ac-backup" data-feature="backup-restore" title="Backup & Restore" data-i18n="btn.backup" onclick="ftthOpenBackup()">
            <span class="ftth-btn-ic"><i class="fa-solid fa-database"></i></span>
            Backup &amp; Restore
        </button>

        <button type="button" class="ftth-btn ftth-ac-perangkat" data-feature="ganti-wifi" title="Perangkat" data-i18n="btn.devices" onclick="ftthOpenDevices()">
            <span class="ftth-btn-ic"><i class="fa-solid fa-hdd"></i></span>
            Perangkat
        </button>

        <button type="button" class="ftth-btn ftth-ac-onu" data-feature="tabel-onu" title="Tabel ONU" data-i18n="btn.onu_table" onclick="ftthOpenOnuTable()">
            <span class="ftth-btn-ic"><i class="fa-solid fa-table-list"></i></span>
            Tabel ONU
        </button>

        <div class="ftth-dropdown">
            <button type="button" class="ftth-btn ftth-ac-queue" data-feature="queue" title="Queue" data-i18n="btn.queue" onclick="ftthToggleQueueMenu(event)">
                <span class="ftth-btn-ic"><i class="fa-solid fa-chart-simple"></i></span>
                Queue <i class="fa-solid fa-caret-down ftth-btn-caret"></i>
            </button>
            <div class="ftth-dropdown-menu" id="ftthQueueMenu" hidden>
                <button type="button" class="ftth-dropdown-item ftth-dd-pppoe" onclick="ftthCloseQueueMenu(); ftthOpenQueue();">
                    <i class="fa-solid fa-network-wired"></i> <span data-i18n="btn.pppoe_list">Daftar PPPoE</span>
                </button>
                <button type="button" class="ftth-dropdown-item ftth-dd-hotspot" onclick="ftthCloseQueueMenu(); ftthOpenHotspot();">
                    <i class="fa-solid fa-wifi"></i> <span data-i18n="btn.hotspot_list">Daftar Hotspot</span>
                </button>
            </div>
        </div>

        <div class="ftth-search ftth-ac-search">
            <input type="text" data-feature="search" data-i18n="search.placeholder" id="ftthSearchInput" placeholder="Cari Lat, Lang, atau nama..." autocomplete="off">
            <button type="button" class="ftth-search-btn" title="Cari" data-i18n="btn.search" onclick="ftthSearch()">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            <div class="ftth-search-suggest" id="ftthSearchSuggest"></div>
        </div>

        <button type="button" class="ftth-icon-btn ftth-ac-lang" id="ftthLangBtn" title="Bahasa" data-i18n="btn.lang" onclick="ftthToggleLang()" style="font-size:11px;font-weight:800;letter-spacing:.03em">
            <span id="ftthLangLabel">EN</span>
        </button>

        <button type="button" class="ftth-icon-btn ftth-ac-theme" data-feature="theme" id="ftthThemeBtn" title="Mode Terang" onclick="ftthToggleTheme()">
            <i class="fa-solid fa-sun" id="ftthThemeIcon"></i>
        </button>

        <button type="button" class="ftth-icon-btn ftth-ac-lock" data-feature="lock" id="ftthLockBtn" title="Kunci Peta" data-i18n="btn.lock" onclick="ftthToggleLock()">
            <i class="fa-solid fa-lock" id="ftthLockIcon"></i>
        </button>

        <span class="ftth-measure-wrap">
            <button type="button" class="ftth-icon-btn ftth-ac-measure" id="ftthMeasureBtn" data-feature="measure" title="Penggaris Ukur" data-i18n="btn.measure" onclick="ftthToggleMeasureMenu()">
                <i class="fa-solid fa-ruler-combined"></i>
            </button>
            <div class="ftth-measure-menu" id="ftthMeasureMenu">
                <button type="button" class="ftth-measure-item" data-mode="ukur" onclick="ftthMeasureStart('ukur')">
                    <i class="fa-solid fa-ruler"></i>
                    <span><strong data-i18n="measure.mode_ruler">Mode Ukur</strong><small data-i18n="measure.mode_ruler_desc">Ukur jarak langsung antar titik di peta</small></span>
                </button>
                <button type="button" class="ftth-measure-item" data-mode="otdr" onclick="ftthMeasureStart('otdr')">
                    <i class="fa-solid fa-wave-square"></i>
                    <span><strong data-i18n="measure.mode_otdr">Mode OTDR</strong><small data-i18n="measure.mode_otdr_desc">Estimasi panjang kabel &amp; redaman fiber</small></span>
                </button>
            </div>
        </span>

        <button type="button" class="ftth-icon-btn ftth-ac-fullscreen ftth-fs-btn" id="ftthFullscreenBtn" data-feature="fullscreen" title="Full Screen" data-i18n="btn.fullscreen" onclick="ftthToggleFullscreen()">
            <i class="fa-solid fa-expand" id="ftthFullscreenIcon"></i>
        </button>

        <button type="button" class="ftth-icon-btn ftth-ac-calculator" data-feature="calculator" title="Kalkulator Redaman" data-i18n="btn.calc" onclick="ftthOpenCalc()">
            <i class="fa-solid fa-calculator"></i>
        </button>

        <button type="button" class="ftth-icon-btn ftth-ac-visibility" data-feature="visibility" title="Visibility" data-i18n="btn.vis" onclick="ftthOpenVis()">
            <i class="fa-solid fa-eye"></i>
        </button>

        <button type="button" class="ftth-icon-btn ftth-ac-users" data-feature="users" title="Users" data-i18n="btn.users" onclick="ftthOpenUsers()">
            <i class="fa-solid fa-users"></i>
        </button>

        <button type="button" class="ftth-icon-btn ftth-ac-anim active" data-feature="anim" id="ftthAnimBtn" title="Matikan Animasi" onclick="ftthToggleAnim()">
            <i class="fa-solid fa-circle-play" id="ftthAnimIcon"></i>
        </button>

        <span class="ftth-notif-wrap">
            <button type="button" class="ftth-icon-btn ftth-ac-notifications" id="ftthNotifBtn" data-feature="notifications" title="Notifikasi" data-i18n="btn.notifications" onclick="ftthToggleNotifMenu()">
                <i class="fa-solid fa-bell"></i>
            </button>
            <div class="ftth-notif-menu" id="ftthNotifMenu">
                <button type="button" class="ftth-notif-item wa" onclick="ftthOpenNotifWa()">
                    <i class="fa-brands fa-whatsapp"></i>
                    <span><strong data-i18n="notif.wa_title">Pengaturan WhatsApp</strong><small data-i18n="notif.wa_desc">URL API, key &amp; nomor pengiriman</small></span>
                </button>
                <button type="button" class="ftth-notif-item tg" onclick="ftthOpenNotifTg()">
                    <i class="fa-brands fa-telegram"></i>
                    <span><strong data-i18n="notif.tg_title">Pengaturan Telegram</strong><small data-i18n="notif.tg_desc">Bot token &amp; chat ID tujuan</small></span>
                </button>
            </div>
        </span>
    </div>

    <div class="ftth-measure-result" id="ftthMeasureResult">
        <div class="ftth-measure-result-head">
            <span class="ftth-measure-result-title" id="ftthMeasureTitle" data-i18n="measure.title">Pengukuran Jarak</span>
            <button type="button" class="ftth-measure-x" title="Tutup" data-i18n="common.close" onclick="ftthMeasureClose()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="ftth-measure-result-body" id="ftthMeasureBody"></div>
        <div class="ftth-measure-result-hint" id="ftthMeasureHint" data-i18n="measure.hint">Klik titik di peta untuk mulai mengukur. Klik kanan / Selesai untuk mengakhiri.</div>
        <div class="ftth-measure-result-actions">
            <button type="button" class="ftth-measure-act" id="ftthMeasureSelesaiBtn" onclick="ftthMeasureSelesai()"><i class="fa-solid fa-check"></i> <span data-i18n="measure.done">Selesai</span></button>
            <button type="button" class="ftth-measure-act" id="ftthMeasureHapusBtn" onclick="ftthMeasureHapus()"><i class="fa-solid fa-trash-can"></i> <span data-i18n="measure.delete">Hapus</span></button>
        </div>
    </div>

    <div class="ftth-measure-result ftth-cable-edit" id="ftthCableEditCard" style="display:none;">
        <div class="ftth-measure-result-head">
            <span class="ftth-measure-result-title"><i class="fa-solid fa-route"></i> <span data-i18n="cable.title">Edit Jalur Kabel</span></span>
            <button type="button" class="ftth-measure-x" title="Tutup" data-i18n="common.close" onclick="ftthCableEditCancel()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="ftth-measure-result-body" id="ftthCableEditBody"></div>
        <div class="ftth-cable-ctl-row">
            <label class="ftth-cable-ctl"><span data-i18n="cable.color">Warna</span><input type="color" id="ftthCableColor" value="#38bdf8" onchange="ftthCableEditStyle()"></label>
            <label class="ftth-cable-ctl"><span data-i18n="cable.thick">Tebal</span><input type="range" id="ftthCableWidth" min="1" max="12" value="3" oninput="ftthCableEditStyle()"><b id="ftthCableWidthVal">3</b></label>
            <label class="ftth-cable-ctl ftth-cable-ctl-check"><input type="checkbox" id="ftthCableCurve" onchange="ftthCableEditStyle()"> <span data-i18n="cable.curve">Lengkung</span></label>
        </div>
        <div class="ftth-measure-result-hint" data-i18n="cable.hint">Klik di peta untuk menambah titik mengikuti jalan (bisa berbelok/menikung). Klik kanan / Enter = Selesai, Esc = Batal, R = Luruskan.</div>
        <div class="ftth-measure-result-actions">
            <button type="button" class="ftth-measure-act" onclick="ftthCableEditFinish()"><i class="fa-solid fa-check"></i> Selesai</button>
            <button type="button" class="ftth-measure-act" onclick="ftthCableEditReset()"><i class="fa-solid fa-slash"></i> Luruskan</button>
            <button type="button" class="ftth-measure-act" onclick="ftthCableEditCancel()"><i class="fa-solid fa-ban"></i> Batal</button>
        </div>
    </div>

    <div class="ftth-cable-props behind" id="ftthCablePropsCard">
        <div class="ftth-cable-props-head">
            <i class="fa-solid fa-route"></i>
            <span id="fcpTitle">Edit Kabel</span>
            <button type="button" title="Tutup" onclick="ftthCablePropsClose()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="ftth-cable-props-body">
            <label class="ftth-cable-ctl"><span>Ukuran Kabel</span><input type="range" id="fcpWidth" min="1" max="12" step="1" value="3" oninput="ftthCablePropsPreview()"><b id="fcpWidthVal">3</b></label>
            <div class="ftth-cable-ctl"><span>Warna Kabel</span><input type="color" id="fcpColor" value="#38bdf8" oninput="ftthCablePropsPreview()"></div>
            <div class="ftth-cable-ctl"><span>Warna Meteor</span><input type="color" id="fcpMeteor" value="#38bdf8" oninput="ftthCablePropsPreview()"></div>
            <label class="ftth-cable-ctl"><span>Animasi</span><select id="fcpAnim" onchange="ftthCablePropsPreview()">
                <option value="">Default</option>
                <option value="none">Tanpa animasi</option>
                <option value="dash">Garis putus berjalan</option>
                <option value="glow-fast">Glow menyala cepat</option>
                <option value="glow-slow">Glow menyala lambat</option>
            </select></label>
            <div class="ftth-cable-ctl" title="Semua kabel memancarkan cahaya sesuai warnanya">
                <span>Aktif Glow Kabel</span>
                <label class="ftth-glow-switch"><input type="checkbox" id="fcpGlow" onchange="ftthToggleCableGlow()" aria-label="Aktif Glow Kabel"><span class="ftth-glow-slider"></span></label>
            </div>
        </div>
        <div class="ftth-cable-props-info" id="fcpInfo"></div>
        <div class="ftth-cable-props-actions">
            <button type="button" class="ftth-odc-btn-lg blue" onclick="ftthCablePropsSave()"><i class="fa-solid fa-check"></i> Simpan</button>
            <button type="button" class="ftth-odc-btn-lg green-dark" onclick="ftthCableRepositionStart()"><i class="fa-solid fa-up-down-left-right"></i> Reposisi</button>
            <button type="button" class="ftth-odc-btn-lg red" title="Hapus kabel yang sudah ada dari peta (permanen)" onclick="ftthCablePropsDelete()"><i class="fa-solid fa-trash-can"></i> Hapus Kabel</button>
        </div>
        <button type="button" class="ftth-cable-cancel-btn" onclick="ftthCablePropsCancel()">Batal Edit</button>
    </div>

    <div class="ftth-reposition-bar" id="ftthRepositionBar">
        <span>Seret titik untuk reposisi · klik garis untuk tambah belokan · klik kanan titik untuk hapus</span>
        <button type="button" class="done" onclick="ftthCableRepositionFinish()">Selesai</button>
        <button type="button" class="cancel" onclick="ftthCableRepositionCancel()">Batal</button>
    </div>

    <div class="ftth-modal-backdrop ftth-calc-backdrop" id="ftthCalcBackdrop" hidden>
        <div class="ftth-calc-wrap" id="ftthCalcWrap">
            <div class="ftth-calc-ref" id="ftthCalcRef">
                <div class="ftth-modal-head ftth-calc-ref-title" id="ftthCalcRefTitle">
                    <i class="fa-solid fa-book-open"></i>
                    Tabel Referensi
                </div>
                <div class="ftth-calc-ref-body">
                    <div class="ftth-calc-ref-section">
                        <div class="ftth-calc-ref-label"><i class="fa-solid fa-code-fork"></i> 1. Splitter Rasio (%) & Loss</div>
                        <table class="ftth-calc-ref-tbl">
                            <thead><tr><th>Rasio</th><th>% Port Kecil</th><th>% Port Besar</th><th>Loss (dB)</th></tr></thead>
                            <tbody>
                                <tr><td>1:99</td><td class="val">1%</td><td class="val">99%</td><td class="val">20.20</td></tr>
                                <tr><td>2:98</td><td class="val">2%</td><td class="val">98%</td><td class="val">17.19</td></tr>
                                <tr><td>3:97</td><td class="val">3%</td><td class="val">97%</td><td class="val">15.43</td></tr>
                                <tr><td>4:96</td><td class="val">4%</td><td class="val">96%</td><td class="val">14.18</td></tr>
                                <tr><td>5:95</td><td class="val">5%</td><td class="val">95%</td><td class="val">13.21</td></tr>
                                <tr><td>6:94</td><td class="val">6%</td><td class="val">94%</td><td class="val">12.22</td></tr>
                                <tr><td>8:92</td><td class="val">8%</td><td class="val">92%</td><td class="val">10.87</td></tr>
                                <tr><td>9:91</td><td class="val">9%</td><td class="val">91%</td><td class="val">10.38</td></tr>
                                <tr><td>10:90</td><td class="val">10%</td><td class="val">90%</td><td class="val">10.20</td></tr>
                                <tr><td>12:88</td><td class="val">12%</td><td class="val">88%</td><td class="val">9.21</td></tr>
                                <tr><td>15:85</td><td class="val">15%</td><td class="val">85%</td><td class="val">8.44</td></tr>
                                <tr><td>20:80</td><td class="val">20%</td><td class="val">80%</td><td class="val">7.19</td></tr>
                                <tr><td>25:75</td><td class="val">25%</td><td class="val">75%</td><td class="val">6.22</td></tr>
                                <tr><td>30:70</td><td class="val">30%</td><td class="val">70%</td><td class="val">5.43</td></tr>
                                <tr><td>40:60</td><td class="val">40%</td><td class="val">60%</td><td class="val">4.18</td></tr>
                                <tr><td>45:55</td><td class="val">45%</td><td class="val">55%</td><td class="val">3.47</td></tr>
                                <tr><td>50:50</td><td class="val">50%</td><td class="val">50%</td><td class="val">3.21</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="ftth-calc-ref-section">
                        <div class="ftth-calc-ref-label"><i class="fa-solid fa-sitemap"></i> <span data-i18n="calc.ref_splitter">2. Passive Splitter PLC &amp; Loss</span></div>
                        <table class="ftth-calc-ref-tbl">
                            <thead><tr><th>Spliter PLC</th><th>Loss (dB)</th></tr></thead>
                            <tbody>
                                <tr><td>1 : 2</td><td class="val">3.25</td></tr>
                                <tr><td>1 : 4</td><td class="val">7.00</td></tr>
                                <tr><td>1 : 8</td><td class="val">10.00</td></tr>
                                <tr><td>1 : 16</td><td class="val">13.50</td></tr>
                                <tr><td>1 : 32</td><td class="val">17.00</td></tr>
                                <tr><td>1 : 64</td><td class="val">20.00</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="ftth-calc-ref-section">
                        <div class="ftth-calc-ref-label"><i class="fa-solid fa-plug"></i> <span data-i18n="calc.ref_component">3. Standar Component Loss</span></div>
                        <div class="ftth-calc-ref-comp">
                            <div class="ftth-calc-ref-comp-row"><span>Splicing Loss</span><b>0.03 dB / titik</b></div>
                            <div class="ftth-calc-ref-comp-row"><span>Kabel Fiber Loss</span><b>0.30 dB / km</b></div>
                            <div class="ftth-calc-ref-comp-row"><span>Fast Connector</span><b>0.30 dB / pc</b></div>
                            <div class="ftth-calc-ref-comp-row"><span>Connector / Adapter</span><b>0.30 dB / pc</b></div>
                            <div class="ftth-calc-ref-comp-row"><span>Pigtail / Patchcord</span><b>0.30 dB / pc</b></div>
                        </div>
                    </div>
                </div>
                <button type="button" class="ftth-calc-ref-close" onclick="ftthCalcToggleRef()" title="Tutup Tabel Referensi" data-i18n="calc.close_ref">
                    <i class="fa-solid fa-xmark"></i> Tutup
                </button>
            </div>

            <div class="ftth-modal-card ftth-calc-card" id="ftthCalcCard">
                <button type="button" class="ftth-calc-kuping" id="ftthCalcKuping" onclick="ftthCalcToggleRef()" title="Tabel Referensi">
                    <i class="fa-solid fa-book-open"></i>
                    <span class="ftth-calc-kuping-label" data-i18n="calc.ref_table">Tabel Referensi</span>
                </button>
                <div class="ftth-modal-head">
                    <i class="fa-solid fa-bolt"></i>
                    <span data-i18n="calc.title">Kalkulator Redaman</span>
                    <button type="button" class="ftth-modal-close" onclick="ftthCloseCalc()" title="Tutup" data-i18n="common.close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="ftth-modal-body">
                    <div class="ftth-calc-form">
                        <div class="ftth-calc-field">
                            <span class="ftth-calc-label"><i class="fa-solid fa-bolt"></i> OLT Transmitter Power / Power (dBm)</span>
                            <div class="ftth-stepper">
                                <button type="button" onclick="ftthCalcFlipSign()" title="Ubah tanda">&plusmn;</button>
                                <input type="number" id="fcInputPower" step="0.5" placeholder="cth: 9 atau -9" autocomplete="off">
                            </div>
                            <div class="ftth-calc-hint">Power input dalam dBm. Tombol &plusmn; untuk ganti tanda.</div>
                        </div>
                        <div class="ftth-calc-row">
                            <div class="ftth-calc-field">
                                <span class="ftth-calc-label"><i class="fa-solid fa-code-fork"></i> <span data-i18n="calc.splitter_ratio">Splitter Rasio</span></span>
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
                                <span class="ftth-calc-label"><i class="fa-solid fa-sitemap"></i> <span data-i18n="calc.splitter_plc">Splitter PLC</span></span>
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
                        <div class="ftth-calc-mode">
                            <button type="button" class="ftth-calc-mode-btn active" id="fcModeSimple" onclick="ftthCalcSetMode('simple')">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> <span data-i18n="calc.simple">Simple Mode</span>
                            </button>
                            <button type="button" class="ftth-calc-mode-btn" id="fcModeAdv" onclick="ftthCalcSetMode('advanced')">
                                <i class="fa-solid fa-sliders"></i> <span data-i18n="calc.advanced">Advanced Mode</span>
                            </button>
                        </div>
                        <div id="fcAdvFields" style="display:none">
                            <div class="ftth-calc-row" style="margin-bottom:8px">
                                <div class="ftth-calc-field">
                                    <span class="ftth-calc-label"><i class="fa-solid fa-plug"></i> <span data-i18n="calc.konektor">Jumlah Konektor</span></span>
                                    <select id="fcConnectors">
                                        <option value="" disabled selected>~0.3 dB / PC</option>
                                        <option value="0">Tanpa konektor</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                    </select>
                                </div>
                                <div class="ftth-calc-field">
                                    <span class="ftth-calc-label"><i class="fa-solid fa-link"></i> <span data-i18n="calc.splice">Sambungan (splice)</span></span>
                                    <input type="number" id="fcSplices" min="0" step="1" placeholder="~0.1 dB / Point" autocomplete="off">
                                </div>
                            </div>
                            <div class="ftth-calc-field">
                                <span class="ftth-calc-label"><i class="fa-solid fa-ruler-horizontal"></i> <span data-i18n="calc.jarak_kabel">Jarak Kabel</span></span>
                                <div class="ftth-calc-inline">
                                    <input type="number" id="fcDistance" min="0" step="0.1" placeholder="Asumsi kabel Loss ~0.35 dB / KM" autocomplete="off">
                                    <select class="ftth-calc-unit" id="fcUnit">
                                        <option value="km" selected>KM</option>
                                        <option value="m">M</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="ftth-calc-result">
                            <div class="ftth-calc-result-title"><i class="fa-solid fa-gauge-high"></i> <span data-i18n="calc.result_title">Hasil Kalkulator</span></div>
                            <div class="ftth-calc-ont-power" id="fcOntPowerBox">
                                <span class="ftth-calc-ont-label">Daya diterima ONT/Modem</span>
                                <b id="fcOntPower">0.00 dBm</b>
                                <div class="ftth-calc-status-inner" id="fcOntStatusBox">
                                    <div class="ftth-calc-status" id="fcOntStatus"></div>
                                </div>
                                <div class="ftth-calc-detail-row ftth-calc-detail-odp" id="fcOdpRow" style="display:none"><span>Sisa Sinyal ODP lanjutan (pass-through):</span> <b id="fcPassPower"></b></div>
                            </div>
                            <div class="ftth-calc-detail" id="fcCalcDetail">
                                <div class="ftth-calc-detail-row ftth-calc-detail-sm"><span>Splitter Loss</span><b id="fcDetailSplit">0.00 dB</b></div>
                                <div class="ftth-calc-detail-row ftth-calc-detail-sm ftth-calc-adv-only"><span>Kabel Fiber Loss</span><b id="fcDetailCable">0.00 dB</b></div>
                                <div class="ftth-calc-detail-row ftth-calc-detail-sm ftth-calc-adv-only"><span>Sambungan (splice)</span><b id="fcDetailSplice">0.00 dB</b></div>
                                <div class="ftth-calc-detail-row ftth-calc-detail-sm ftth-calc-adv-only"><span>Konektor</span><b id="fcDetailConn">0.00 dB</b></div>
                                <div class="ftth-calc-detail-row ftth-calc-detail-total" id="fcDetailTotalRow"><span>Total Optical Loss</span><b id="fcDetailTotal">0.00 dB</b></div>
                            </div>
                        </div>
                    </div>
                    <div class="ftth-calc-note" id="ftthCalcNote"><i class="fa-solid fa-circle-info" style="margin-right:3px;opacity:.7"></i>Rentang optimal ONT: -15 dBm s/d -24 dBm. Di atas -27 dBm dapat menyebabkan LOS.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthVisBackdrop" hidden>
        <div class="ftth-modal-card ftth-vis-card" id="ftthVisCard">
            <div class="ftth-modal-head">
                <i class="fa-solid fa-eye"></i>
                Visibility
                <button type="button" class="ftth-modal-close" onclick="ftthCloseVis()" title="Tutup" data-i18n="common.close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-vis-section"><i class="fa-solid fa-map-location-dot"></i> <span data-i18n="vis.fitur_peta">Fitur Peta</span></div>
                <div class="ftth-vis-grid ftth-vis-grid-col-5">
                    <label class="ftth-vis-check"><input type="checkbox" id="visRouter" checked><span>Router</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visOdc" checked><span>ODC</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visOdp" checked><span>ODP</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visOtb" checked><span>OTB</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visClosure" checked><span>Closure/JB</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visOnuOnline" checked><span>ONU Aktif</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visOnuOffline" checked><span>ONU NonAktif</span></label>
                </div>
                <div class="ftth-vis-section"><i class="fa-solid fa-tag"></i> <span data-i18n="vis.tampilkan_teks_onu">Tampilkan Teks ONU</span></div>
                <select id="visOnuText" class="ftth-vis-select">
                    <option value="nama"><span data-i18n="vis.tampilkan_nama">Tampilkan Nama</span></option>
                    <option value="pppoe"><span data-i18n="vis.tampilkan_pppoe">Tampilkan PPPoE</span></option>
                    <option value="sembunyi" selected><span data-i18n="vis.sembunyikan_teks">Sembunyikan Teks</span></option>
                </select>
                <div class="ftth-vis-inline">
                    <label class="ftth-vis-check"><input type="checkbox" id="visCable" checked><span><i class="fa-solid fa-diagram"></i> <span data-i18n="vis.jalur_kabel">Jalur Kabel</span></span></label>
                </div>
                <div class="ftth-vis-section ftth-vis-collapsible" id="ftthVisHideSection" onclick="ftthToggleVisSection('ftthVisHideSection','ftthVisHideBody')">
                    <span class="ftth-vis-section-label"><i class="fa-solid fa-eye-slash"></i> <span data-i18n="vis.sembunyikan_tombol">Sembunyikan Tombol</span></span>
                    <i class="fa-solid fa-chevron-down ftth-vis-chevron"></i>
                </div>
                <div class="ftth-vis-grid ftth-vis-grid-col" id="ftthVisHideBody" hidden>
                    <label class="ftth-vis-check" data-feature="sync-mikrotik"><input type="checkbox" id="visHideMikrotik"><span>Sync Mikrotik</span></label>
                    <label class="ftth-vis-check" data-feature="sync-olt"><input type="checkbox" id="visHideOlt"><span>Sync OLT</span></label>
                    <label class="ftth-vis-check" data-feature="sync-genieacs"><input type="checkbox" id="visHideGenieacs"><span>Sync GenieACS</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideBackup"><span>Backup/restore</span></label>
                    <label class="ftth-vis-check" data-feature="ganti-wifi"><input type="checkbox" id="visHidePerangkat"><span>Perangkat</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideOnu"><span>Tabel ONU</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideQueue"><span>Queue</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideLock"><span>Kunci Peta</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideMeasure"><span>Ukur Jarak</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideFs"><span>Full screen</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideCalc"><span>Kalkulator Redaman</span></label>
                    <label class="ftth-vis-check" data-feature="users"><input type="checkbox" id="visHideUsers"><span>Users</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideAnim"><span>Animasi</span></label>
                    <label class="ftth-vis-check"><input type="checkbox" id="visHideNotif"><span>Notifikasi</span></label>
                    <label class="ftth-vis-check" data-feature="edit-map"><input type="checkbox" id="visHideFab"><span>Tombol +</span></label>
                </div>
            </div>
            <div class="ftth-vis-note"><i class="fa-solid fa-circle-info"></i> <span data-i18n="vis.onu_teks">Centang untuk menampilkan elemen, kosongkan untuk menyembunyikannya.</span></div>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthUsersBackdrop" hidden>
        <div class="ftth-modal-card ftth-users-card" id="ftthUsersCard">
            <div class="ftth-modal-head">
                <i class="fa-solid fa-users"></i>
                <span data-i18n="users.title">Pengaturan User</span>
                <button type="button" class="ftth-modal-close" onclick="ftthCloseUsers()" title="Tutup" data-i18n="common.close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body ftth-users-body">
                <div class="ftth-users-list" id="ftthUsersList">
                    <div class="ftth-user-empty" style="text-align:center;padding:20px 0"><div class="ftth-a-loader"><svg viewBox="-2 -2 58 52"><path class="ftth-a-chevron" d="M6 38 L26 8 L46 38"/><g class="ftth-a-check-group"><path class="ftth-a-check" d="M22 26 C10 30 16 44 28 34 C36 26 42 20 44 19"/><circle class="ftth-a-tip" cx="50" cy="17" r="2.5"/></g></svg></div><div style="font-size:12px;color:#a78bfa"><span data-i18n="loader.users">Memuat data user...</span></div></div>
                </div>
            </div>
            <div class="ftth-users-foot">
                <button type="button" class="ftth-user-add" onclick="ftthUserShowForm(null)"><i class="fa-solid fa-user-plus"></i> <span data-i18n="users.add">Tambah User</span></button>
                <button type="button" class="ftth-user-close" onclick="ftthCloseUsers()">Tutup</button>
            </div>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthUserFormBackdrop" hidden>
        <div class="ftth-modal-card ftth-users-card" id="ftthUserFormCard">
            <div class="ftth-modal-head">
                <i class="fa-solid fa-user-plus"></i>
                <span id="ftthUserFormTitle" data-i18n="users.add">Tambah User</span>
                <button type="button" class="ftth-modal-close" onclick="ftthUserBackToList()" title="Kembali" data-i18n="device.kembali">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body ftth-users-body">
                <div class="ftth-user-form">
                    <label class="ftth-user-field">
                        <span>Username</span>
                        <input type="text" id="ftthUserName" placeholder="Username unik" autocomplete="off">
                    </label>
                    <label class="ftth-user-field">
                        <span>Password</span>
                        <div class="ftth-user-input-wrap">
                            <input type="password" id="ftthUserPass" placeholder="Kosongkan jika tidak ubah" data-i18n="user.kosongkan_hint">
                            <button type="button" class="ftth-user-pw-toggle" id="ftthUserPassToggle" onclick="ftthUserTogglePass()" title="Tampilkan sandi" data-i18n="user.tampilkan_sandi" tabindex="-1">
                                <i class="fa-solid fa-eye" id="ftthUserPassToggleIcon"></i>
                            </button>
                        </div>
                    </label>
                    <label class="ftth-user-field">
                        <span>Role</span>
                        <select id="ftthUserRole">
                            <option value="admin">Admin</option>
                            <option value="noc">NOC</option>
                            <option value="teknisi">Teknisi</option>
                            <option value="sales">Sales</option>
                        </select>
                    </label>
                    <div class="ftth-user-perm-title"><span data-i18n="user.hak_akses">Hak Akses</span> <small>(untuk Role Sales)</small></div>
                    <div class="ftth-user-perm-list">
                        <label class="ftth-user-perm"><input type="checkbox" id="ftthPermEditMap"> <span data-i18n="user.perm_edit_map">Buat Edit Map (Tambah/Hapus/Ubah)</span></label>
                        <label class="ftth-user-perm"><input type="checkbox" id="ftthPermMikrotik"> <span data-i18n="user.perm_sync_mk">Bisa sync Mikrotik</span></label>
                        <label class="ftth-user-perm"><input type="checkbox" id="ftthPermOlt"> <span data-i18n="user.perm_sync_olt">Bisa sync OLT</span></label>
                        <label class="ftth-user-perm"><input type="checkbox" id="ftthPermGenieacs"> <span data-i18n="user.perm_sync_acs">Bisa sync GenieAcs</span></label>
                        <label class="ftth-user-perm"><input type="checkbox" id="ftthPermWifi"> <span data-i18n="user.perm_ganti_wifi">Bisa Ganti WiFi</span></label>
                        <label class="ftth-user-perm"><input type="checkbox" id="ftthPermExcel"> <span data-i18n="user.perm_import_export">Bisa Import/Export Excel</span></label>
                        <label class="ftth-user-perm"><input type="checkbox" id="ftthPermPanel"> Bisa Akses Panel FTTH</label>
                    </div>
                </div>
            </div>
            <div class="ftth-users-foot">
                <button type="button" class="ftth-user-add" id="ftthUserSaveBtn" onclick="ftthUserSave()"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
                <button type="button" class="ftth-user-close" onclick="ftthUserBackToList()">Batal</button>
            </div>
        </div>
    </div>

    <div class="ftth-fab-group">
        <button type="button" class="ftth-fab" title="Menu Utama" data-i18n="fab.menu_utama" data-feature="edit-map" onclick="ftthOpenAddDevice()">
            <i class="fa-solid fa-plus"></i>
            <span data-i18n="fab.menu_utama">Menu Utama</span>
        </button>
        <div class="ftth-fab-trigger-wrap">
            <button type="button" class="ftth-fab-trigger" id="ftthFabTrigger" onclick="ftthToggleStyles()" title="Gaya Peta" data-i18n="fab.gaya_peta">
                <i class="fa-solid fa-layer-group" id="ftthFabTriggerIcon"></i>
            </button>
            <div class="ftth-map-styles" id="ftthMapStyles">
                <button type="button" class="ftth-style-btn" data-layer="peta" onclick="ftthSetLayer('peta')" title="Peta" data-i18n="fab.peta"><i class="fa-solid fa-map"></i><small data-i18n="fab.peta">Peta</small></button>
                <button type="button" class="ftth-style-btn active" data-layer="satelit" onclick="ftthSetLayer('satelit')" title="Satelit" data-i18n="fab.satelit"><i class="fa-solid fa-satellite"></i><small data-i18n="fab.satelit">Satelit</small></button>
                <button type="button" class="ftth-style-btn" data-layer="dark" onclick="ftthSetLayer('dark')" title="Dark"><i class="fa-solid fa-moon"></i><small>Dark</small></button>
                <button type="button" class="ftth-style-btn" data-layer="light" onclick="ftthSetLayer('light')" title="Light"><i class="fa-solid fa-sun"></i><small>Light</small></button>
            </div>
        </div>
    </div>

    <div class="ftth-status">
        <span class="ftth-status-item"><span class="ftth-status-dot online"></span> <span data-i18n="status.pppoe_on">PPPoE Online:</span> <b id="ftthPppoeOnline">{{ $pppoeOnline }}</b></span>
        <span class="ftth-status-item"><span class="ftth-status-dot offline"></span> <span data-i18n="status.pppoe_off">PPPoE Offline:</span> <b id="ftthPppoeOffline">{{ $pppoeOffline }}</b></span>
        <span class="ftth-status-item"><span class="ftth-status-dot online"></span> <span data-i18n="status.onu_on">ONU Online:</span> <b id="ftthOnuOnline">{{ $onuOnline }}</b></span>
        <span class="ftth-status-item"><span class="ftth-status-dot offline"></span> <span data-i18n="status.onu_off">ONU Offline:</span> <b id="ftthOnuOffline">{{ $onuOffline }}</b></span>
    </div>

    <div class="ftth-copyright"><i class="fa-regular fa-copyright"></i> {{ now()->year }} PT. Alkonek Network Access. All rights reserved.</div>

    <div class="ftth-modal-backdrop" id="ftthMikrotikBackdrop" hidden>
        <div class="ftth-modal-card" id="ftthMikrotikCard">
            <div class="ftth-modal-head">
                <i class="fa-solid fa-server"></i>
                Sync Mikrotik
                <span class="ftth-mt-status" id="ftthMtStatus"></span>
                <button type="button" class="ftth-modal-close" onclick="ftthCloseMikrotik()" title="Tutup" data-i18n="common.close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-form">
                    <div class="ftth-form-grid2">
                        <div class="ftth-form-cell">
                            <label for="mtIp"><span data-i18n="sync.mikrotik_ip">IP Lokal Mikrotik</span></label>
                            <input type="text" id="mtIp" placeholder="172.10.0.1" autocomplete="off">
                        </div>
                        <div class="ftth-form-cell">
                            <label for="mtPort"><span data-i18n="sync.mikrotik_port">Port API</span></label>
                            <input type="number" id="mtPort" placeholder="80" min="1" max="65535">
                        </div>
                        <div class="ftth-form-cell">
                            <label for="mtUser"><span data-i18n="users.username">Username</span></label>
                            <input type="text" id="mtUser" placeholder="admin" autocomplete="off">
                        </div>
                        <div class="ftth-form-cell">
                            <label for="mtPass"><span data-i18n="users.password">Password</span></label>
                            <input type="password" id="mtPass" autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="ftth-form-actions">
                    <button type="button" class="ftth-modal-btn save" onclick="ftthSaveMikrotik()"><i class="fa-solid fa-floppy-disk"></i> <span data-i18n="common.save">Simpan</span></button>
                    <button type="button" class="ftth-modal-btn" onclick="ftthConnectMikrotik()"><i class="fa-solid fa-plug"></i> <span data-i18n="sync.konek">Konek</span></button>
                    <button type="button" class="ftth-modal-btn syncall" onclick="ftthSyncAllMikrotik()"><i class="fa-solid fa-rotate"></i> Sync All Saved Routes</button>
                </div>
                <div class="ftth-mt-wan" id="ftthMtWanBox">
                    <div class="ftth-mt-wan-head">
                        <span class="ftth-mt-wan-title"><i class="fa-solid fa-chart-line"></i> Trafik WAN-ISP</span>
                        <span class="ftth-mt-wan-rate rx"><i></i>Rx <b id="ftthMtWanRx">-</b></span>
                        <span class="ftth-mt-wan-rate tx"><i></i>Tx <b id="ftthMtWanTx">-</b></span>
                    </div>
                    <div class="ftth-mt-wan-chart"><canvas id="ftthMtWanChart"></canvas></div>
                    <div class="ftth-mt-wan-status" id="ftthMtWanStatus"></div>
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
                <button type="button" class="ftth-modal-close" onclick="ftthCloseOlt()" title="Tutup" data-i18n="common.close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-form">
                    <div class="ftth-form-grid2">
                        <div class="ftth-form-cell">
                            <label for="oltIp"><span data-i18n="sync.olt_ip">IP OLT</span></label>
                            <input type="text" id="oltIp" placeholder="172.10.10.2" autocomplete="off">
                        </div>
                        <div class="ftth-form-cell">
                            <label for="oltPort"><span data-i18n="sync.olt_port">Port SSH</span></label>
                            <input type="number" id="oltPort" placeholder="22" min="1" max="65535">
                        </div>
                        <div class="ftth-form-cell">
                            <label for="oltUser">Username</label>
                            <input type="text" id="oltUser" placeholder="root" autocomplete="off">
                        </div>
                        <div class="ftth-form-cell">
                            <label for="oltPass">Password</label>
                            <input type="password" id="oltPass" autocomplete="off">
                        </div>
                    </div>
                    <div class="ftth-form-cell">
                        <label for="oltBrand"><span data-i18n="sync.olt_brand">Brand OLT</span></label>
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
                </div>
                <div class="ftth-form-actions">
                    <button type="button" class="ftth-modal-btn save" onclick="ftthSaveOlt()"><i class="fa-solid fa-floppy-disk"></i> <span data-i18n="common.save">Simpan</span></button>
                    <button type="button" class="ftth-modal-btn" onclick="ftthConnectOlt()"><i class="fa-solid fa-plug"></i> <span data-i18n="sync.konek">Konek</span></button>
                    <button type="button" class="ftth-modal-btn syncall" onclick="ftthSyncAllOlt()"><i class="fa-solid fa-rotate"></i> Sync All Saved OLT</button>
                </div>
                <div class="ftth-mt-wan" id="ftthOltPonBox">
                    <div class="ftth-mt-wan-head">
                        <span class="ftth-mt-wan-title"><i class="fa-solid fa-chart-line"></i> Trafik PON 1</span>
                        <span class="ftth-mt-wan-rate rx"><i></i>Rx <b id="ftthOltPonRx">-</b></span>
                        <span class="ftth-mt-wan-rate tx"><i></i>Tx <b id="ftthOltPonTx">-</b></span>
                    </div>
                    <div class="ftth-mt-wan-chart"><canvas id="ftthOltPonChart"></canvas></div>
                    <div class="ftth-mt-wan-status" id="ftthOltPonStatus"></div>
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
                <button type="button" class="ftth-modal-close" onclick="ftthCloseGenieacs()" title="Tutup" data-i18n="common.close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-form">
                    <label for="genieacsUrl"><span data-i18n="sync.acs_url">URL GenieAcs NBI</span></label>
                    <input type="text" id="genieacsUrl" placeholder="http://192.168.1.10:7557" autocomplete="off">
                </div>
                <div class="ftth-form-actions">
                    <button type="button" class="ftth-modal-btn save" onclick="ftthSaveGenieacsConfig()"><i class="fa-solid fa-floppy-disk"></i> <span data-i18n="sync.simpan_config">Simpan Config</span></button>
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
                <button type="button" class="ftth-modal-close" onclick="ftthCloseNotifWa()" title="Tutup" data-i18n="common.close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">
                <label class="ftth-vis-check"><input type="checkbox" id="notifWaEnabled"><span><strong data-i18n="notif.wa_active">Aktifkan WhatsApp</strong></span></label>
                <div class="ftth-form">
                    <label for="notifWaUrl"><span data-i18n="notif.wa_url">URL API WhatsApp</span></label>
                    <input type="text" id="notifWaUrl" placeholder="https://api.whatsapp.com/send" autocomplete="off">
                    <label for="notifWaKey"><span data-i18n="notif.wa_api_key">API Key</span></label>
                    <input type="password" id="notifWaKey" placeholder="Masukkan API key" autocomplete="off">
                    <label for="notifWaSender"><span data-i18n="notif.wa_sender">Nomor Pengirim</span></label>
                    <input type="text" id="notifWaSender" placeholder="628xxxxxxxxxx" autocomplete="off">
                    <label for="notifWaRecipient"><span data-i18n="notif.wa_dest">Nomor Tujuan</span></label>
                    <input type="text" id="notifWaRecipient" placeholder="628xxxxxxxxxx" autocomplete="off">
                </div>
                <div class="ftth-form-actions">
                    <button type="button" class="ftth-modal-btn ftth-btn-batal" onclick="ftthCloseNotifWa()"><i class="fa-solid fa-xmark"></i> Batal</button>
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
                <button type="button" class="ftth-modal-close" onclick="ftthCloseNotifTg()" title="Tutup" data-i18n="common.close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">
                <label class="ftth-vis-check"><input type="checkbox" id="notifTgEnabled"><span><strong data-i18n="notif.tg_active">Aktifkan Telegram</strong></span></label>
                <div class="ftth-form">
                    <label for="notifTgToken"><span data-i18n="notif.bot_token">Bot Token</span></label>
                    <input type="password" id="notifTgToken" placeholder="123456:ABC-DEF..." autocomplete="off">
                    <label for="notifTgChatId"><span data-i18n="notif.chat_id">Chat ID Tujuan</span></label>
                    <input type="text" id="notifTgChatId" placeholder="-100xxxxxxxxxx" autocomplete="off">
                </div>
                <div class="ftth-form-actions">
                    <button type="button" class="ftth-modal-btn ftth-btn-batal" onclick="ftthCloseNotifTg()"><i class="fa-solid fa-xmark"></i> Batal</button>
                    <button type="button" class="ftth-modal-btn save" onclick="ftthSaveNotifTg()"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
                </div>
            </div>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthQueueBackdrop" hidden>
        <div class="ftth-modal-card" id="ftthQueueCard">
            <div class="ftth-modal-head">
                <i class="fa-solid fa-network-wired" style="color:#22d3ee;"></i>
                Daftar PPPoE
                <span class="ftth-mt-status" id="ftthQueueStatus"></span>
                <button type="button" class="ftth-modal-close" onclick="ftthCloseQueue()" title="Tutup" data-i18n="common.close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-queue-toolbar">
                    <input type="text" id="ftthQueueSearch" placeholder="Cari nama / SN / OLT / IP..." data-i18n="queue.search" autocomplete="off">
                    <button type="button" class="ftth-modal-btn" onclick="ftthRefreshQueue()"><i class="fa-solid fa-rotate"></i> Refresh</button>
                </div>
                <div class="ftth-queue-list-wrap" id="ftthQueueWrap">
                    <div class="ftth-router-empty"><div class="ftth-a-loader"><svg viewBox="-2 -2 58 52"><path class="ftth-a-chevron" d="M6 38 L26 8 L46 38"/><g class="ftth-a-check-group"><path class="ftth-a-check" d="M22 26 C10 30 16 44 28 34 C36 26 42 20 44 19"/><circle class="ftth-a-tip" cx="50" cy="17" r="2.5"/></g></svg></div> Memuat PPPoE client...</div>
                </div>
            </div>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthHotspotBackdrop" hidden>
        <div class="ftth-modal-card" id="ftthHotspotCard">
            <div class="ftth-modal-head">
                <i class="fa-solid fa-wifi" style="color:#fb923c;"></i>
                Daftar Hotspot
                <span class="ftth-mt-status" id="ftthHotspotStatus"></span>
                <button type="button" class="ftth-modal-close" onclick="ftthCloseHotspot()" title="Tutup" data-i18n="common.close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-queue-toolbar">
                    <input type="text" id="ftthHotspotSearch" placeholder="Cari nama / serial..." data-i18n="hotspot.search" autocomplete="off">
                    <button type="button" class="ftth-modal-btn" onclick="ftthRefreshHotspot()"><i class="fa-solid fa-rotate"></i> Refresh</button>
                </div>
                <div class="ftth-queue-list-wrap" id="ftthHotspotWrap">
                    <div class="ftth-router-empty"><div class="ftth-a-loader"><svg viewBox="-2 -2 58 52"><path class="ftth-a-chevron" d="M6 38 L26 8 L46 38"/><g class="ftth-a-check-group"><path class="ftth-a-check" d="M22 26 C10 30 16 44 28 34 C36 26 42 20 44 19"/><circle class="ftth-a-tip" cx="50" cy="17" r="2.5"/></g></svg></div> Memuat ONU Hotspot...</div>
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
                <button type="button" class="ftth-modal-close" onclick="ftthCloseBackup()" title="Tutup" data-i18n="common.close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="ftth-modal-body">

                <div class="ftth-bs ftth-bs-1">
                    <div class="ftth-bs-head"><i class="fa-solid fa-envelope"></i> <span data-i18n="backup.auto_gmail_section">Auto Backup ke Gmail</span> <span class="ftth-bs-tag"><span data-i18n="backup.harian">Harian</span></span></div>
                    <div class="ftth-form ftth-bs-form">
                        <div>
                            <label for="backupEmail"><span data-i18n="backup.email_label">Email penerima Backup</span></label>
                            <input type="email" id="backupEmail" placeholder="admin@alkonek.net" autocomplete="off">
                        </div>
                        <div>
                            <label for="backupTime"><span data-i18n="backup.jam_label">Jam Backup</span></label>
                            <input type="text" id="backupTime" placeholder="00:00" autocomplete="off">
                        </div>
                    </div>
                    <details class="ftth-smtp-adv">
                        <summary><i class="fa-solid fa-gear"></i> Pengaturan Pengirim Email — isi sekali saja</summary>
                        <div class="ftth-form ftth-bs-form">
                            <div>
                                <label for="smtpHost"><i class="fa-solid fa-server"></i> SMTP Host</label>
                                <input type="text" id="smtpHost" placeholder="smtp.gmail.com" autocomplete="off">
                            </div>
                            <div>
                                <label for="smtpPort"><i class="fa-solid fa-plug"></i> Port</label>
                                <input type="text" id="smtpPort" placeholder="587" autocomplete="off">
                            </div>
                            <div>
                                <label for="smtpUsername"><i class="fa-solid fa-user"></i> Email Pengirim (SMTP User)</label>
                                <input type="text" id="smtpUsername" placeholder="alkoneknetworkaccess@gmail.com" autocomplete="off">
                            </div>
                            <div>
                                <label for="smtpPassword"><i class="fa-solid fa-key"></i> App Password (16 karakter)</label>
                                <input type="password" id="smtpPassword" placeholder="" autocomplete="new-password">
                            </div>
                        </div>
                    </details>
                    <div class="ftth-bs-actions">
                        <button type="button" class="ftth-backup-btn ftth-bu-1" onclick="ftthSaveBackup()"><i class="fa-solid fa-floppy-disk"></i> <span data-i18n="backup.simpan_backup">Simpan Backup</span></button>
                        <button type="button" class="ftth-backup-btn ftth-bu-2" onclick="ftthSendBackupNow()"><i class="fa-solid fa-paper-plane"></i> <span data-i18n="backup.kirim_sekarang">Kirim Sekarang</span></button>
                    </div>
                </div>

                <div class="ftth-bs ftth-bs-2">
                    <div class="ftth-bs-head"><i class="fa-solid fa-file-arrow-up"></i> <span data-i18n="backup.restore_section">Restore File JSON</span></div>
                    <div class="ftth-bs-actions">
                        <button type="button" class="ftth-backup-btn ftth-bu-3" onclick="ftthRestoreFile('database')"><i class="fa-solid fa-database"></i> Restore database.json</button>
                        <button type="button" class="ftth-backup-btn ftth-bu-4" onclick="ftthRestoreFile('routers')"><i class="fa-solid fa-server"></i> Restore Routers.json</button>
                    </div>
                </div>

                <div class="ftth-bs ftth-bs-3">
                    <div class="ftth-bs-head"><i class="fa-solid fa-file-excel"></i> <span data-i18n="backup.excel_section">Backup &amp; Restore Data Excel</span></div>
                    <div class="ftth-bs-actions">
                        <button type="button" class="ftth-backup-btn ftth-bu-5" onclick="ftthImportExcel()"><i class="fa-solid fa-file-import"></i> Import Data Excel</button>
                        <button type="button" class="ftth-backup-btn ftth-bu-6" onclick="ftthExportExcel()"><i class="fa-solid fa-file-export"></i> Export Data Excel</button>
                    </div>
                </div>

                <div class="ftth-bs ftth-bs-4">
                    <div class="ftth-bs-head"><i class="fa-solid fa-earth-asia"></i> <span data-i18n="backup.kmz_section">Sinkronisasi Google Earth (KMZ)</span></div>
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
                <span class="ftth-modal-title"> <span id="ftthAddDeviceTitle" data-i18n="device.add">Tambah Perangkat</span></span>
                <span class="ftth-device-status" id="ftthDeviceStatus"></span>
                <button type="button" class="ftth-modal-close" onclick="ftthCloseAddDevice()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-df">
                    <label><span data-i18n="device.type">Type</span></label>
                    <select id="ftthDeviceType" onchange="ftthRenderDeviceFields()">
                        <option value="" data-i18n="device.type_select">— Pilih Type —</option>
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
                    <label data-i18n="device.parent">Induk</label>
                    <select id="ftthDevParent">
                        <option value="">None</option>
                    </select>
                </div>
                <div class="ftth-df">
                    <label><span data-i18n="device.name">Nama/ID Pelanggan</span></label>
                    <input type="text" id="ftthDevName" placeholder="e.g. ODP Gang 5 / OLT MA5800" autocomplete="off">
                </div>
                <div id="ftthDevExtra"></div>
                <div class="ftth-core-chk" id="ftthCoreChkWrap" hidden>
                    <label class="ftth-core-chk-label">
                        <input type="checkbox" id="ftthDevCoreMgmt" onchange="ftthRenderDeviceFields()">
                        <span class="ftth-core-chk-box"><i class="fa-solid fa-check"></i></span>
                        <span data-i18n="device.mgmt_core">Aktifkan Management Core</span>
                    </label>
                </div>
                <div id="ftthCoreFields" hidden></div>
                <input type="hidden" id="ftthDevLat">
                <input type="hidden" id="ftthDevLng">
                <input type="hidden" id="ftthDevLocation">
                <div class="ftth-form-actions">
                    <button type="button" class="ftth-modal-btn ftth-btn-batal" onclick="ftthCloseAddDevice()"><i class="fa-solid fa-xmark"></i> <span data-i18n="common.cancel">Batal</span></button>
                    <button type="button" class="ftth-modal-btn save" onclick="ftthSaveDevice()"><i class="fa-solid fa-floppy-disk"></i> <span data-i18n="common.save">Simpan</span></button>
                </div>
            </div>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthDevicesBackdrop" hidden>
        <div class="ftth-modal-card ftth-device-card ftth-devices-list-card" id="ftthDevicesCard">
            <div class="ftth-modal-head" id="ftthDevicesHead"></div>
            <div class="ftth-modal-body">
                <div class="ftth-device-cats" id="ftthDevicesCats">
                    <div class="ftth-device-empty"><div class="ftth-a-loader"><svg viewBox="-2 -2 58 52"><path class="ftth-a-chevron" d="M6 38 L26 8 L46 38"/><g class="ftth-a-check-group"><path class="ftth-a-check" d="M22 26 C10 30 16 44 28 34 C36 26 42 20 44 19"/><circle class="ftth-a-tip" cx="50" cy="17" r="2.5"/></g></svg></div><br><span data-i18n="loader.devices">Memuat data perangkat...</span></div>
                </div>
                <div class="ftth-device-browse" id="ftthDevicesBrowse" hidden>
                    <div class="ftth-browse-toolbar">
                        <div class="ftth-browse-search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="ftthBrowseSearch" placeholder="Cari data..." data-i18n="browse.cari" oninput="ftthBrowseFilter()">
                        </div>
                        <button type="button" class="ftth-browse-delall" onclick="ftthBrowseDeleteAll()"><span data-i18n="browse.hapus_semua">Hapus Semua</span> (<span id="ftthBrowseDelCount">0</span>)</button>
                    </div>
                    <div class="ftth-device-list" id="ftthBrowseList"></div>
                    <button type="button" class="ftth-browse-close" onclick="ftthCloseDevices()">Tutup</button>
                </div>
            </div>
        </div>
    </div>

    <div class="ftth-modal-backdrop" id="ftthOnuTableBackdrop" hidden>
        <div class="ftth-modal-card ftth-device-card ftth-onu-table-card" id="ftthOnuTableCard">
            <div class="ftth-modal-head ftth-onu-table-head">
                <span class="ftth-modal-title"><i class="fa-solid fa-table-list"></i> <span data-i18n="onu.title">Tabel ONU</span></span>
                    <div class="ftth-onu-table-tools">
                        <div class="ftth-onu-table-search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="ftthOnuTableSearch" data-i18n="onu.search" placeholder="Cari nama / type / akun / IP / ODP / OLT..." oninput="ftthOnuTableFilter()">
                        </div>
                        <div class="ftth-onu-dd">
                        <button type="button" class="ftth-onu-table-btn ftth-btn-print" title="Print PDF"><i class="fa-solid fa-print"></i> <span data-i18n="onu.print_pdf">Print PDF</span> <i class="fa-solid fa-caret-down" style="margin-left:2px"></i></button>
                        <div class="ftth-onu-dd-menu">
                            <button type="button" class="ftth-onu-dd-item ftth-dd-all" onclick="ftthOnuTablePrint()"><i class="fa-solid fa-list"></i> <span data-i18n="onu.semua">Semua</span></button>
                            <button type="button" class="ftth-onu-dd-item ftth-dd-ppp" onclick="ftthOnuTablePrint('ppp')"><i class="fa-solid fa-cube"></i> <span data-i18n="onu.pppp_only">PPPoE Saja</span></button>
                            <button type="button" class="ftth-onu-dd-item ftth-dd-hotspot" onclick="ftthOnuTablePrint('hotspot')"><i class="fa-solid fa-wifi"></i> <span data-i18n="onu.hotspot_only">Hotspot Saja</span></button>
                        </div>
                    </div>
                    <div class="ftth-onu-dd">
                        <button type="button" class="ftth-onu-table-btn ftth-btn-export" title="Export"><i class="fa-solid fa-file-export"></i> Export <i class="fa-solid fa-caret-down" style="margin-left:2px"></i></button>
                        <div class="ftth-onu-dd-menu">
                            <button type="button" class="ftth-onu-dd-item ftth-dd-all" onclick="ftthOnuTableExport()"><i class="fa-solid fa-list"></i> <span data-i18n="onu.semua">Semua</span></button>
                            <button type="button" class="ftth-onu-dd-item ftth-dd-ppp" onclick="ftthOnuTableExport('ppp')"><i class="fa-solid fa-cube"></i> <span data-i18n="onu.pppp_only">PPPoE Saja</span></button>
                            <button type="button" class="ftth-onu-dd-item ftth-dd-hotspot" onclick="ftthOnuTableExport('hotspot')"><i class="fa-solid fa-wifi"></i> <span data-i18n="onu.hotspot_only">Hotspot Saja</span></button>
                        </div>
                    </div>
                    <button type="button" class="ftth-modal-close ftth-onu-table-close" onclick="ftthCloseOnuTable()"><i class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            <div class="ftth-modal-body">
                <div class="ftth-onu-table-wrap">
                    <table class="ftth-onu-table">
                        <thead>
                            <tr>
                                <th data-i18n="onu.no">No</th>
                                <th data-i18n="onu.name">Nama</th>
                                <th data-i18n="onu.type">Type</th>
                                <th data-i18n="onu.account">Akun PPPoE</th>
                                <th data-i18n="onu.ip">IP Address</th>
                                <th data-i18n="onu.coord">Koordinat</th>
                                <th data-i18n="onu.htb">HTB</th>
                                <th data-i18n="onu.odp">ODP</th>
                                <th data-i18n="onu.olt">OLT</th>
                            </tr>
                        </thead>
                        <tbody id="ftthOnuTableBody">
                            <tr><td colspan="9" class="ftth-device-empty ftth-onu-loading"><div class="ftth-a-loader"><svg viewBox="-2 -2 58 52"><path class="ftth-a-chevron" d="M6 38 L26 8 L46 38"/><g class="ftth-a-check-group"><path class="ftth-a-check" d="M22 26 C10 30 16 44 28 34 C36 26 42 20 44 19"/><circle class="ftth-a-tip" cx="50" cy="17" r="2.5"/></g></svg></div> <span data-i18n="loader.onu">Memuat data ONU...</span></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="ftth-onu-table-footer">
                    <span class="ftth-onu-table-info" id="ftthOnuTableInfo">Menampilkan 1-0 dari 0 data</span>
                    <div class="ftth-onu-table-pager">
                        <button type="button" class="ftth-onu-table-page-btn" id="ftthOnuPagePrev" onclick="ftthOnuPageGo('prev')"><span data-i18n="onu.sebelumnya">Sebelumnya</span></button>
                        <span class="ftth-onu-table-page-num" id="ftthOnuPageNum">1/1</span>
                        <button type="button" class="ftth-onu-table-page-btn" id="ftthOnuPageNext" onclick="ftthOnuPageGo('next')"><span data-i18n="onu.selanjutnya">Selanjutnya</span></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ftth-detail-card" id="ftthDetailCard" hidden>
        <div class="ftth-detail-head">
            <i class="fa-solid fa-grip-vertical ftth-detail-grip" title="Seret untuk memindahkan kartu" data-i18n="detail.seret"></i>
            <span class="ftth-device-type-badge" id="ftthDetailBadge" style="background:#94a3b8">DEVICE</span>
            <span class="ftth-detail-name" id="ftthDetailName">-</span>
            <button type="button" class="ftth-modal-close" onclick="ftthCloseDetail()" title="Tutup" data-i18n="common.close">
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
    @php
        $ftthUser = auth()->user();
        $ftthPermKeys = ['edit_map','sync_mikrotik','sync_olt','sync_genieacs','ganti_wifi','import_export','panel_ftth'];
        $ftthPerms = (array) ($ftthUser->permissions ?? []);
        if (empty($ftthPerms)) $ftthPerms = array_fill_keys($ftthPermKeys, true);
    @endphp
    window.FTTH_USER = {
        role: '{{ $ftthUser->role }}',
        permissions: @json($ftthPerms)
    };

    /* ── Language / Translation system ── */
    var FTTH_LANG = localStorage.getItem('ftth_lang') || 'id';
    var FTTH_I18N = {
        id: {
            /* toolbar */
            'btn.sync_mikrotik': 'Sync Mikrotik', 'btn.sync_olt': 'Sync OLT', 'btn.sync_genieacs': 'Sync GenieACS',
            'btn.backup': 'Backup & Restore', 'btn.devices': 'Perangkat', 'btn.onu_table': 'Tabel ONU',
            'btn.queue': 'Queue', 'btn.pppoe_list': 'Daftar PPPoE', 'btn.hotspot_list': 'Daftar Hotspot',
            'btn.theme_light': 'Mode Terang', 'btn.theme_dark': 'Mode Gelap',
            'btn.lock': 'Kunci Peta', 'btn.unlock': 'Buka Kunci Peta',
            'btn.fullscreen': 'Full Screen', 'btn.exit_fullscreen': 'Keluar Fullscreen',
            'btn.users': 'Users', 'btn.calc': 'Kalkulator Redaman', 'btn.vis': 'Visibility',
            'btn.measure': 'Penggaris Ukur', 'btn.notifications': 'Notifikasi',
            'btn.search': 'Cari', 'btn.lang': 'Bahasa',
            'btn.anim_off': 'Matikan Animasi', 'btn.anim_on': 'Nyalakan Animasi',
            /* search */
            'search.placeholder': 'Cari Lat, Lang, atau nama...',
            /* measure */
            'measure.title': 'Pengukuran Jarak', 'measure.done': 'Selesai', 'measure.delete': 'Hapus',
            'measure.mode_ruler': 'Mode Ukur', 'measure.mode_ruler_desc': 'Ukur jarak langsung antar titik di peta',
            'measure.mode_otdr': 'Mode OTDR', 'measure.mode_otdr_desc': 'Cek titik berdasarkan jarak kabel dari alat OTDR',
            'measure.hint': 'Klik titik di peta untuk mulai mengukur. Klik kanan / Selesai untuk mengakhiri.',
            'measure.active_ruler': 'Mode Ukur aktif — klik titik untuk mengukur jarak',
            'measure.active_otdr': 'Mode OTDR aktif — klik Titik 1 (Start) di peta',
            'measure.done_msg': 'Pengukuran selesai', 'measure.need_pts': 'Klik minimal 2 titik di peta',
            /* cable edit */
            'cable.title': 'Edit Jalur Kabel', 'cable.color': 'Warna', 'cable.thick': 'Tebal', 'cable.curve': 'Lengkung',
            'cable.hint': 'Klik di peta untuk menambah titik mengikuti jalan. Klik kanan / Enter = Selesai, Esc = Batal, R = Luruskan.',
            /* calculator */
            'calc.title': 'Kalkulator Redaman', 'calc.ref_table': 'Tabel Referensi', 'calc.close_ref': 'Tutup Tabel Referensi', 'calc.flip_sign': 'Ganti tanda + / -', 'calc.splitter_ratio': 'Splitter Rasio',
            'calc.splitter_plc': 'Splitter PLC', 'calc.simple': 'Simple Mode', 'calc.advanced': 'Advanced Mode',
            'calc.input_power': 'Input Power OLT/ODC (dBm)', 'calc.cable_length': 'Jarak Kabel (m)',
            'calc.result': 'Hasil Kalkulator', 'calc.total_loss': 'Total Loss (dB)',
            'calc.signal_strong': 'Sinyal Terlalu Kuat', 'calc.signal_optimal': 'Unggul / Optimal',
            'calc.signal_warn': 'Peringatan / Batas', 'calc.signal_bad': 'Risiko Atenuasi Buruk',
            /* detail card */
            'detail.device': 'DEVICE', 'detail.customer': 'PELANGGAN', 'detail.type': 'Tipe', 'detail.status': 'Status',
            'detail.paket': 'Paket', 'detail.deadline': 'Tenggat', 'detail.pppoe': 'PPPoE',
            'detail.serial': 'Serial', 'detail.mac': 'MAC', 'detail.ip': 'IP',
            'detail.mgmt_core': 'Management Core', 'detail.ya': 'Ya', 'detail.edit': 'Edit',
            'detail.map': 'Peta', 'detail.coordinate': 'Koordinat',
            /* ONU table */
            'onu.title': 'Tabel ONU', 'onu.no': 'No', 'onu.name': 'Nama', 'onu.type': 'Type',
            'onu.account': 'Akun PPPoE', 'onu.ip': 'IP Address', 'onu.coord': 'Koordinat',
            'onu.htb': 'HTB', 'onu.odp': 'ODP', 'onu.olt': 'OLT',
            'onu.prev': 'Sebelumnya', 'onu.next': 'Selanjutnya',
            'onu.loading': 'Memuat data ONU...', 'onu.empty': 'Belum ada data ONU.', 'onu.no_data': 'Tidak ada data.',
            'onu.search': 'Cari nama / type / akun / IP / ODP / OLT...',
            /* visibility */
            'vis.title': 'Visibility', 'vis.router': 'Router', 'vis.odc': 'ODC', 'vis.odp': 'ODP',
            'vis.otb': 'OTB', 'vis.closure': 'Closure/JB', 'vis.onu_online': 'ONU Aktif', 'vis.onu_offline': 'ONU NonAktif',
            /* users */
            'users.title': 'Pengaturan User', 'users.add': 'Tambah User', 'users.edit': 'Edit User', 'users.close': 'Tutup',
            'users.username': 'Username', 'users.password': 'Password', 'users.role': 'Role',
            'users.permissions': 'Hak Akses', 'users.name': 'Nama Lengkap', 'users.email': 'Email',
            'users.role_admin': 'ADMIN', 'users.role_noc': 'NOC', 'users.role_sales': 'SALES', 'users.role_tech': 'TEKNISI',
            'users.empty': 'Belum ada akun user.', 'users.loading': 'Memuat data user...',
            /* sync cards */
            'sync.mikrotik_title': 'Sync Mikrotik', 'sync.olt_title': 'Sync OLT', 'sync.genieacs_title': 'Sync GenieACS',
            'sync.mikrotik_ip': 'IP Lokal Mikrotik', 'sync.mikrotik_port': 'Port API',
            'sync.olt_ip': 'IP OLT', 'sync.olt_port': 'Port SSH', 'sync.olt_brand': 'Brand OLT',
            'sync.acs_url': 'URL GenieAcs NBI',
            /* notifications */
            'notif.wa_title': 'Pengaturan WhatsApp', 'notif.tg_title': 'Pengaturan Telegram',
            'notif.wa_desc': 'URL API, key & nomor pengiriman', 'notif.tg_desc': 'Bot token & chat ID tujuan',
            'notif.wa_active': 'Aktifkan WhatsApp', 'notif.tg_active': 'Aktifkan Telegram',
            'notif.bot_token': 'Bot Token', 'notif.chat_id': 'Chat ID Tujuan',
            /* backup */
            'backup.title': 'Backup & Restore', 'backup.auto_gmail': 'Auto Backup ke Gmail',
            'backup.email': 'Email penerima Backup', 'backup.import_excel': 'Import Data Excel',
            'backup.export_excel': 'Export Data Excel', 'backup.import_kml': 'Import KML/KMZ',
            'backup.export_kmz': 'Export Data KMZ',
            /* device form */
            'device.add': 'Tambah Perangkat', 'device.type': 'Type', 'device.type_select': '-- Pilih Type --',
            'device.parent': 'Induk', 'device.none': 'None', 'device.name': 'Nama/ID Pelanggan', 'device.name_device': 'Nama Perangkat',
            'device.mgmt_core': 'Aktifkan Management Core',
            /* status bar */
            'status.pppoe_on': 'PPPoE Online:', 'status.pppoe_off': 'PPPoE Offline:',
            'status.onu_on': 'ONU Online:', 'status.onu_off': 'ONU Offline:',
            /* device categories */
            'cat.router': 'Router', 'cat.olt': 'OLT', 'cat.otb': 'OTB',
            'cat.odc': 'ODC', 'cat.odp': 'ODP', 'cat.htb': 'HTB',
            'cat.onu_pppoe': 'ONU (PPPoE)', 'cat.onu_hotspot': 'ONU (Hotspot)',
            /* common */
            'common.loading': 'Memuat...', 'common.save': 'Simpan', 'common.cancel': 'Batal',
            'common.delete': 'Hapus', 'common.close': 'Tutup', 'common.ok': 'OK',
            'common.back_noc': 'Kembali ke NOC Dashboard',
            'common.yes': 'Ya', 'common.no': 'Tidak', 'common.confirm': 'Konfirmasi',
            'common.saved': 'Tersimpan', 'common.deleted': 'Dihapus',
            'common.save_fail': 'Gagal simpan', 'common.delete_fail': 'Gagal hapus',
            'common.connect_ok': 'Konek OK', 'common.connect_fail': 'Konek gagal',
            'common.no_data': 'Tidak ada data', 'common.location_found': 'Lokasi ditemukan',
            'common.data_loaded': 'Data dimuat', 'common.saving': 'Menyimpan...',
            'confirm.delete_customer': 'Hapus pelanggan',
            'confirm.delete_router': 'Router ini?', 'confirm.delete_olt': 'OLT ini?',
            /* toasts — toolbar */
            'toast.map_locked': 'Peta terkunci', 'toast.map_unlocked': 'Peta dibuka kunci',
            'toast.light_mode': 'Mode terang aktif', 'toast.dark_mode': 'Mode gelap aktif',
            'toast.anim_off': 'Animasi dimatikan', 'toast.anim_on': 'Animasi diaktifkan',
            'toast.exit_fullscreen': 'Keluar dari Full Screen',
            /* toasts — measure/cable */
            'toast.cable_hint': 'Klik di peta untuk menambah titik jalur kabel mengikuti jalan',
            'toast.cable_saved': 'Jalur kabel disimpan', 'toast.cable_save_fail': 'Gagal menyimpan jalur kabel',
            'toast.cable_cancelled': 'Edit jalur kabel dibatalkan',
            'toast.need_points': 'Klik minimal 2 titik di peta',
            /* toasts — users */
            'toast.user_load_fail': 'Gagal memuat user', 'toast.user_deleted': 'User dihapus',
            'toast.user_saved': 'User tersimpan',
            'toast.user_delete_fail': 'Gagal menghapus', 'toast.user_save_fail': 'Gagal menyimpan',
            /* toasts — search */
            'toast.type_first': 'Ketik koordinat atau alamat terlebih dahulu',
            'toast.searching': 'Mencari',
            'toast.not_found': 'Lokasi tidak ditemukan',
            'toast.search_fail': 'Gagal mencari lokasi. Periksa koneksi.',
            'toast.no_coords': 'Tidak ada koordinat untuk',
            /* toasts — router */
            'toast.fill_ip': 'Isi IP lokal Mikrotik', 'toast.fill_port': 'Isi port API',
            'toast.fill_user': 'Isi username', 'toast.fill_pass': 'Isi password',
            'toast.router_saved': 'Router tersimpan', 'toast.router_save_fail': 'Gagal menyimpan router',
            'toast.save_first': 'Simpan router dulu untuk konek',
            'toast.router_synced': 'router berhasil', 'toast.sync_fail': 'Gagal sync',
            'toast.sync_all_fail': 'Gagal sync semua router',
            'toast.router_sync_fail': 'Sync router gagal',
            'toast.router_deleted': 'Router dihapus', 'toast.router_delete_fail': 'Gagal hapus router',
            /* toasts — OLT */
            'toast.fill_olt_ip': 'Isi IP OLT', 'toast.fill_olt_port': 'Isi port SSH',
            'toast.olt_saved': 'OLT tersimpan', 'toast.olt_save_fail': 'Gagal menyimpan OLT',
            'toast.save_olt_first': 'Simpan OLT dulu untuk konek',
            'toast.olt_synced': 'OLT berhasil', 'toast.olt_sync_fail': 'Sync OLT gagal',
            'toast.olt_sync_all_fail': 'Gagal sync semua OLT',
            'toast.olt_deleted': 'OLT dihapus', 'toast.olt_delete_fail': 'Gagal hapus OLT',
            /* toasts — GenieACS */
            'toast.config_saved': 'Config tersimpan', 'toast.config_save_fail': 'Gagal simpan config',
            'toast.genieacs_sync_fail': 'Gagal sync GenieACS',
            /* toasts — notifications */
            'toast.wa_saved': 'Pengaturan WhatsApp tersimpan',
            'toast.tg_saved': 'Pengaturan Telegram tersimpan',
            'toast.settings_save_fail': 'Gagal simpan pengaturan',
            /* toasts — backup */
            'toast.config_save_ok': 'Konfigurasi tersimpan',
            'toast.config_save_err': 'Gagal simpan konfigurasi',
            'toast.backup_sent': 'Backup terkirim', 'toast.backup_fail': 'Gagal kirim backup',
            'toast.restore_done': 'Restore selesai', 'toast.restore_fail': 'Gagal restore',
            'toast.import_done': 'Import selesai', 'toast.import_fail': 'Gagal import',
            /* toasts — device */
            'toast.device_saved': 'Perangkat disimpan',
            'toast.no_coords_data': 'Data belum memiliki titik koordinat di peta',
            'toast.goto': 'Menuju:',
            'toast.searching_for': 'Mencari',
            'toast.customer_delete_fail': 'Gagal menghapus pelanggan',
            'toast.no_data_to_delete': 'Tidak ada data untuk dihapus',
            'toast.data_delete_fail': 'Gagal menghapus data',
            'toast.status_change_fail': 'Gagal mengubah status',
            'toast.device_delete_fail': 'Gagal menghapus perangkat',
            'toast.export_fail': 'Gagal mengexport data',
            'toast.copy_fail': 'Gagal menyalin',
            'toast.auto_sync_bg': 'Auto-sync diproses di background...',
            'toast.auto_sync_done': 'Auto-sync selesai —',
            'toast.no_sync_data': 'tidak ada data',
            /* innerHTML / loader messages */
            'loader.router': 'Memuat data router...', 'loader.router_fail': 'Gagal memuat daftar router',
            'loader.olt': 'Memuat data OLT...', 'loader.olt_fail': 'Gagal memuat daftar OLT',
            'loader.pppoe': 'Mengambil data PPPoE client...', 'loader.pppoe_fail': 'Gagal memuat data PPPoE client',
            'loader.pppoe_empty': 'Tidak ada PPPoE client aktif.',
            'loader.pppoe_hint': 'Pastikan router sudah tersimpan &amp; disinkronkan.',
            'loader.hotspot': 'Mengambil data ONU Hotspot...', 'loader.hotspot_fail': 'Gagal memuat data ONU Hotspot',
            'loader.hotspot_empty': 'Tidak ada ONU Hotspot terdaftar.',
            'loader.hotspot_hint': 'Pastikan pelanggan bertype hotspot sudah memiliki ONU ter-link.',
            'loader.onu': 'Memuat data ONU...', 'loader.onu_fail': 'Gagal memuat data.',
            'loader.onu_empty': 'Belum ada data ONU.',
            'loader.devices': 'Memuat data perangkat...',
            'loader.users': 'Memuat data user...',
            'loader.no_results': 'Tidak ada hasil untuk pencarian',
            /* queue/hotspot status */
            'qstatus.loading': 'Memuat...', 'qstatus.load_fail': 'Gagal memuat data',
            'qstatus.fail': 'Gagal memuat',
            'qstatus.pppoe_active': 'data PPPoE client tersedia',
            'qstatus.pppoe_empty': 'Tidak ada PPPoE client aktif.',
            'qstatus.pppoe_hint': 'Pastikan router sudah tersimpan & disinkronkan.',
            'qstatus.hotspot_empty': 'Tidak ada ONU Hotspot terdaftar.',
            'qstatus.hotspot_hint': 'Pastikan pelanggan bertype hotspot sudah memiliki ONU ter-link.',
            /* device categories */
            'cat.router_induk': 'Router',
            /* detail card labels */
            'detail.induk': 'Induk',
            /* ONU table info */
            'onu.info': 'Menampilkan',
            'onu.info_of': 'dari',
            'onu.info_data': 'data',
            'onu.empty_msg': 'Belum ada data ONU.',
            /* generic */
            'msg.tidak_ada_hasil': 'Tidak ada hasil',
            'msg.no_data': 'Tidak ada data.',
            'msg.gagal_load': 'Gagal memuat data.',
            'msg.aktifkan_animasi': 'Aktifkan Animasi', 'msg.matikan_animasi': 'Matikan Animasi',
            'msg.no_pppoe_active': 'Tidak ada PPPoE client aktif.',
            'msg.no_hotspot_registered': 'Tidak ada ONU Hotspot terdaftar.',
            'msg.pastikan_router_sync': 'Pastikan router sudah tersimpan &amp; disinkronkan.',
            'msg.pastikan_onu_linked': 'Pastikan pelanggan bertype hotspot sudah memiliki ONU ter-link.',
            'msg.mengambil_data_acs': 'Memuat info ACS:',
            'loader.router_empty': 'Belum ada router tersimpan',
            'loader.olt_empty': 'Belum ada OLT tersimpan',
            'loader.sync_empty': 'Belum ada hasil sync',
            'loader.pppoe_search_empty': 'Tidak ada hasil untuk pencarian',
            'loader.hotspot_search_empty': 'Tidak ada hasil untuk pencarian',
            'loader.no_search': 'Tidak ada hasil',
            'msg.koneksi_ok': 'Koneksi OK',
            /* lock */
            'msg.lock_map': 'Kunci Peta', 'msg.unlock_map': 'Buka Kunci Peta',
            /* detail card location */
            'detail.tidak_diketahui': 'Tidak diketahui', 'detail.mencari_alamat': 'Mencari alamat...',
            /* detail card status */
            'detail.status_online': 'ONLINE', 'detail.status_offline': 'OFFLINE', 'detail.status_set': 'SET',
            /* device form labels */
            'device.kapasitas_port': 'Jumlah Kapasitas Port', 'device.ip_address': 'IP Address',
            'device.user_pppoe': 'User PPPoE', 'device.jumlah_pon': 'Jumlah PON',
            /* management core */
            'device.core_fields': 'Management Core',
            /* user empty */
            'users.empty': 'Belum ada akun user.',
            /* device search */
            'device.tidak_ditemukan': 'Tidak ditemukan.', 'device.belum_ada_data': 'Belum ada data.',
            /* detail card badge */
            'detail.badge_pelanggan': 'PELANGGAN',
            /* sync button */
            'btn.syncing': 'Menyinkronkan...',
            /* log messages */
            'log.gagal_load_detail': 'Gagal memuat detail pelanggan',
            'log.gagal_load_network': 'Gagal memuat detail pelanggan (jaringan)',
            'log.ping': 'Ping ...',
            'log.gagal_ping': 'Gagal ping',
            'log.gagal_ping_net': 'Gagal ping (jaringan)',
            'log.no_acs_device': 'Belum ada perangkat ACS tersambung untuk pelanggan ini',
            'log.loading_acs': 'Memuat info ACS:',
            'log.gagal_ambil_acs': 'Gagal ambil data ACS',
            'log.gagal_ambil_acs_net': 'Gagal ambil data ACS (jaringan)',
            'log.mengambil_ssid': 'Mengambil SSID / WiFi ...',
            'log.gagal_ambil_ssid': 'Gagal ambil SSID',
            'log.gagal_ambil_ssid_net': 'Gagal ambil SSID (jaringan)',
            'log.no_onu_reboot': 'Pelanggan tidak memiliki ONU (OLT) untuk direboot',
            'log.reboot_sending': 'Mengirim perintah reboot ...',
            'log.reboot_sent': 'Perintah reboot terkirim',
            'log.gagal_reboot': 'Gagal reboot',
            'log.gagal_reboot_net': 'Gagal reboot (jaringan)',
            'log.duplikat_sending': 'Membuat duplikat ...',
            'log.duplikat_hint': 'buka halaman Edit untuk menyesuaikan',
            'log.gagal_duplikat': 'Gagal duplikat',
            'log.gagal_duplikat_net': 'Gagal duplikat (jaringan)',
            /* calc reference table */
            'calc.ref_splitter': 'Passive Splitter PLC & Loss',
            'calc.ref_sp_loss': 'Loss (dB)',
            'calc.ref_component': 'Standar Component Loss',
            'calc.ref_splice': 'Splicing Loss', 'calc.ref_splice_val': '0.03 dB / titik',
            'calc.ref_kabel': 'Kabel Fiber Loss', 'calc.ref_kabel_val': '0.30 dB / km',
            'calc.ref_fc': 'Fast Connector', 'calc.ref_fc_val': '0.30 dB / pc',
            'calc.ref_connector': 'Connector / Adapter', 'calc.ref_connector_val': '0.30 dB / pc',
            'calc.ref_pigtail': 'Pigtail / Patchcord', 'calc.ref_pigtail_val': '0.30 dB / pc',
            'calc.flip_hint': 'Ganti tanda + / -', 'calc.placeholder': 'cth: 9 atau -9',
            'calc.input_hint': 'Power input dalam dBm. Tombol ± untuk ganti tanda.',
            'calc.no_ratio': 'Tanpa Rasio', 'calc.no_plc': 'Tanpa PLC',
            'calc.konektor': 'Jumlah Konektor', 'calc.konektor_hint': '~0.3 dB / PC',
            'calc.tanpa_konektor': 'Tanpa konektor',
            'calc.splice': 'Sambungan (splice)', 'calc.splice_hint': '~0.1 dB / Point',
            'calc.jarak_kabel': 'Jarak Kabel', 'calc.jarak_hint': 'Asumsi kabel Loss ~0.35 dB / KM',
            'calc.result_title': 'Hasil Kalkulator',
            'calc.power_received': 'Daya diterima ONT/Modem',
            'calc.pass_through': 'Sisa Sinyal ODP lanjutan (pass-through):',
            'calc.loss_splitter': 'Splitter Loss', 'calc.loss_kabel': 'Kabel Fiber Loss',
            'calc.loss_splice': 'Sambungan (splice)', 'calc.loss_konektor': 'Konektor',
            'calc.total_loss_detail': 'Total Optical Loss',
            'calc.note_optimal': 'Rentang optimal ONT: -15 dBm s/d -24 dBm. Di atas -27 dBm dapat menyebabkan LOS.',
            /* visibility panel */
            'vis.fitur_peta': 'Fitur Peta',
            'vis.router_induk': 'Router', 'vis.odc_distribusi': 'ODC',
            'vis.odp_point': 'ODP', 'vis.otb_pusat': 'OTB',
            'vis.tampilkan_teks_onu': 'Tampilkan Teks ONU',
            'vis.tampilkan_nama': 'Tampilkan Nama',
            'vis.tampilkan_pppoe': 'Tampilkan PPPoE',
            'vis.sembunyikan_teks': 'Sembunyikan Teks',
            'vis.jalur_kabel': 'Jalur Kabel',
            'vis.sembunyikan_tombol': 'Sembunyikan Tombol',
            'vis.onu_teks': 'Centang untuk menampilkan elemen, kosongkan untuk menyembunyikannya.',
            /* user form */
            'user.kosongkan_hint': 'Kosongkan jika tidak ubah',
            'user.tampilkan_sandi': 'Tampilkan sandi',
            'user.hak_akses': 'Hak Akses (untuk Role Sales)',
            'user.perm_edit_map': 'Buat Edit Map (Tambah/Hapus/Ubah)',
            'user.perm_sync_mk': 'Bisa sync Mikrotik',
            'user.perm_sync_olt': 'Bisa sync OLT',
            'user.perm_sync_acs': 'Bisa sync GenieAcs',
            'user.perm_ganti_wifi': 'Bisa Ganti WiFi',
            'user.perm_import_export': 'Bisa Import/Export Excel',
            /* menu / fab */
            'fab.menu_utama': 'Menu Utama', 'fab.peta': 'Peta', 'fab.satelit': 'Satelit',
            'fab.gaya_peta': 'Gaya Peta',
            /* sync card form labels */
            'sync.konek': 'Konek', 'sync.simpan_config': 'Simpan Config', 'sync.sync_acs': 'Syncing with ACS',
            'sync.sync_all_routes': 'Sync All Saved Routes',
            'sync.sync_all_olt': 'Sync All Saved OLT',
            'sync.user_pppoe': 'User PPPoE',
            /* notification form */
            'notif.wa_url': 'URL API WhatsApp', 'notif.wa_api_key': 'API Key',
            'notif.wa_sender': 'Nomor Pengirim', 'notif.wa_dest': 'Nomor Tujuan',
            /* backup form */
            'backup.auto_gmail_section': 'Auto Backup ke Gmail', 'backup.harian': 'Harian',
            'backup.email_label': 'Email penerima Backup', 'backup.jam_label': 'Jam Backup',
            'backup.simpan_backup': 'Simpan Backup', 'backup.kirim_sekarang': 'Kirim Sekarang',
            'backup.restore_section': 'Restore File JSON',
            'backup.restore_db': 'Restore database.json', 'backup.restore_routers': 'Restore Routers.json',
            'backup.excel_section': 'Backup & Restore Data Excel',
            'backup.kmz_section': 'Sinkronisasi Google Earth (KMZ)',
            /* device form */
            'device.placeholder': 'e.g. ODP Gang 5 / OLT MA5800',
            'device.edit': 'Edit Perangkat',
            'device.add_onu': 'Tambah Perangkat — ONU',
            /* browse */
            'browse.cari': 'Cari data...', 'browse.hapus_semua': 'Hapus Semua',
            'browse.jelajahi': 'Jelajahi Data', 'browse.kembali': 'Kembali',
            'browse.daftar': 'Daftar',
            'browse.perangkat': 'perangkat',
            'browse.open_settings': 'Buka pengaturan', 'browse.goto_point': 'Menuju ke titik di peta',
            /* ONU table toolbar */
            'onu.print_pdf': 'Print PDF', 'onu.semua': 'Semua', 'onu.pppp_only': 'PPPoE Saja', 'onu.hotspot_only': 'Hotspot Saja',
            'onu.sebelumnya': 'Sebelumnya', 'onu.selanjutnya': 'Selanjutnya',
            /* detail card */
            'detail.seret': 'Seret untuk memindahkan kartu',
            'detail.hapus_btn': 'Hapus', 'detail.edit_btn': 'Edit', 'detail.status_btn': 'Status',
            'detail.edit_kabel': 'Edit Kabel',
            'detail.detail_label': 'Detail', 'detail.brand': 'Brand', 'detail.model': 'Model',
            'detail.kapasitas': 'Kapasitas',
            'detail.mgmt_core_label': 'Management Core',
            'detail.open_settings': 'Buka pengaturan', 'detail.goto_peta': 'Menuju ke titik di peta',
            /* ODC card */
            'odc.title': 'Perangkat ODC', 'odc.type': 'Tipe', 'odc.port_usage': 'Port Usage',
            'odc.sisa': 'Sisa', 'odc.onu_per_jalur': 'Total ONU per Port (Jalur)',
            'odc.mgmt_core': 'Manajemen Core', 'odc.mgmt_aktif': 'Aktif', 'odc.mgmt_nonaktif': 'Nonaktif',
            'odc.uptime': 'Uptime', 'odc.loading': 'Memuat data ODC...', 'odc.distance': 'Jarak',
            'odc.salin': 'Salin', 'odc.maps': 'Maps', 'odc.wa': 'WA', 'odc.edit': 'Edit', 'odc.duplikat': 'Duplikat',
            /* customer detail */
            'cust.tipe': 'Tipe', 'cust.aktif': 'Aktif', 'cust.ditangguhkan': 'Ditangguhkan',
            'cust.paket': 'Paket', 'cust.odp': 'ODP', 'cust.port_odp': 'Port ODP',
            'cust.port_olt': 'Port OLT', 'cust.tenggat': 'Tenggat', 'cust.serial': 'Serial', 'cust.mac': 'MAC',
            'cust.onu_sesi': 'ONU & Sesi', 'cust.ip_publik': 'IP Publik',
            'cust.mati': 'Mati', 'cust.memuat': 'MEMUAT...',
            'cust.lokasi': 'Lokasi', 'cust.koordinat': 'Koordinat',
            /* customer action buttons */
            'cust.btn_ping': 'PING', 'cust.btn_status': 'Status', 'cust.btn_acs': 'ACS',
            'cust.btn_wifi': 'Wifi', 'cust.btn_reboot': 'Reboot', 'cust.btn_salin': 'Salin',
            'cust.btn_gmaps': 'Buka di Google Maps', 'cust.btn_wa': 'Chat WhatsApp',
            'cust.btn_edit': 'Edit pelanggan', 'cust.btn_duplikat': 'Duplikat pelanggan',
            /* ping/acs/wifi results */
            'result.host': 'Host:', 'result.status': 'Status:', 'result.latency': 'Latency:',
            'result.jitter': 'Jitter:', 'result.pkt_loss': 'Packet loss:',
            'result.device': 'Device:', 'result.pabrikan': 'Pabrikan:', 'result.produk': 'Produk:',
            'result.firmware': 'Firmware:', 'result.mode': 'Mode:',
            'result.ssid': 'SSID:', 'result.wifi': 'WiFi:', 'result.channel': 'Channel:',
            /* copy text */
            'copy.status': 'Status:', 'copy.ip': 'IP:', 'copy.port_olt': 'Port OLT:',
            'copy.port_odp': 'Port ODP:', 'copy.paket': 'Paket:', 'copy.lokasi': 'Lokasi:',
            'copy.koordinat': 'Koordinat:', 'copy.maps': 'Maps:', 'copy.wa': 'WA:', 'copy.edit': 'Edit:',
            'copy.ringkasan': 'Ringkasan',
            /* map popup */
            'popup.online': '● Online', 'popup.offline': '● Offline',
            /* measure result labels */
            'measure.otdr_title': 'OTDR — Estimasi',
            'measure.jarak_lurus': 'Jarak garis lurus', 'measure.panjang_kabel': 'Panjang kabel',
            'measure.redaman_total': 'Redaman total', 'measure.ruas_titik': 'Ruas / Titik',
            'measure.total_jarak': 'Total jarak', 'measure.titik': 'Titik',
            'measure.otdr_hint': 'Estimasi: kabel × redaman dB/km + sambungan.',
            'measure.otdr_hint2': 'Klik titik-titik pada jalur kabel. Selesai / klik kanan untuk mengakhiri.',
            'measure.result_hint_done': 'Pengukuran selesai. Klik Hapus untuk mengulang.',
            'measure.result_hint_active': 'Klik titik di peta untuk menambah ruas.',
            'measure.pengukuran_jarak': 'Pengukuran Jarak',
            /* cable edit */
            'cable.titik': 'Titik', 'cable.panjang_jalur': 'Panjang jalur',
            /* search */
            'search.goto_label': 'Koordinat:',
            /* genieacs summary */
            'acs.online': 'Online', 'acs.offline': 'Offline',
            'acs.device_aktif': 'GenieACS device aktif', 'acs.device_tidak_aktif': 'GenieACS device tidak aktif',
            'acs.total_device': 'Total device', 'acs.onu_connected': 'ONU tersambung',
            /* confirm dialogs */
            'confirm.hapus_user': 'Hapus user ini?',
            'confirm.hapus_router': 'Hapus router ini?',
            'confirm.hapus_olt': 'Hapus OLT ini?',
            'confirm.hapus_pelanggan': 'Hapus pelanggan',
            'confirm.hapus_semua': 'Yakin hapus semua',
            'confirm.data_in': 'data ini?',
            'confirm.hapus_perangkat': 'Hapus perangkat ini?',
            'confirm.reboot_onu': 'Reboot ONU',
            'confirm.duplikat_pelanggan': 'Duplikat pelanggan',
            /* status bar dynamic */
            'status.user_pppoe': 'user PPPoE', 'status.onu': 'ONU',
            'status.sync_ok': 'Sync OK', 'status.konek_ok': 'Konek OK',
            'status.online': 'Online', 'status.offline': 'Offline',
            'status.genieacs_active': 'GenieACS device aktif', 'status.genieacs_inactive': 'GenieACS device tidak aktif',
            'status.total_device': 'Total device', 'status.onu_tersambung': 'ONU tersambung',
            /* queue item */
            'queue.sn': 'SN:', 'queue.olt': 'OLT:', 'queue.odp': 'ODP:', 'queue.add': 'ADD',
            'queue.search': 'Cari nama / SN / OLT / IP...',
            'hotspot.search': 'Cari nama / serial...',
            /* device validation */
            'device.pilih_type': 'Pilih type perangkat terlebih dahulu',
            'device.nama_wajib': 'Nama perangkat wajib diisi',
            'device.menysimpan': 'Menyimpan...', 'device.tersimpan': 'Tersimpan',
            'device.gagal_simpan': 'Gagal simpan', 'device.gagal_menyimpan': 'Gagal menyimpan',
            'device.daftar_perangkat': 'Daftar Perangkat', 'device.perangkat': 'perangkat',
            'device.jelajahi': 'Jelajahi Data', 'device.daftar': 'Daftar',
            'device.kembali': 'Kembali',
            /* sync status messages */
            'sync.menyimpan': 'Menyimpan...', 'sync.gagal_simpan': 'Gagal simpan',
            'sync.tersimpan': 'Tersimpan', 'sync.menghubungkan': 'Menghubungkan...',
            'sync.konek_gagal': 'Konek gagal', 'sync.menyinkronkan_semua': 'Menyinkronkan semua...',
            'sync.gagal': 'Gagal', 'sync.gagal_sync': 'Gagal sync', 'sync.sync_gagal': 'Sync gagal',
            'sync.menghapus': 'Menghapus...', 'sync.gagal_hapus': 'Gagal hapus',
            'sync.router_dihapus': 'Router dihapus', 'sync.olt_dihapus': 'OLT dihapus',
            'sync.config_tersimpan': 'Config tersimpan',
            'sync.menyinkronkan': 'Menyinkronkan...', 'sync.menyiapkan_backup': 'Menyiapkan backup & mengirim...',
            'sync.terkirim': 'Terkirim', 'sync.gagal_kirim': 'Gagal kirim',
            'sync.memulihkan': 'Memulihkan...', 'sync.restore_selesai': 'Restore selesai',
            'sync.gagal_restore': 'Gagal restore', 'sync.mengimpor_excel': 'Mengimpor data Excel...',
            'sync.gagal_import': 'Gagal import', 'sync.import_selesai': 'Import selesai',
            'sync.mengimpor_kmz': 'Mengimpor KML/KMZ...',
            'sync.gagal_simpan_pengaturan': 'Gagal simpan pengaturan',
            /* tag marker */
            'tag.silahkan_geser': 'Silahkan geser pin',
            /* queue hotspot loader */
            'loader.pppp_client': 'Memuat PPPoE client...', 'loader.hotspot_onu': 'Memuat ONU Hotspot...',
        },
        en: {
            /* toolbar */
            'btn.sync_mikrotik': 'Sync Mikrotik', 'btn.sync_olt': 'Sync OLT', 'btn.sync_genieacs': 'Sync GenieACS',
            'btn.backup': 'Backup & Restore', 'btn.devices': 'Devices', 'btn.onu_table': 'ONU Table',
            'btn.queue': 'Queue', 'btn.pppoe_list': 'PPPoE List', 'btn.hotspot_list': 'Hotspot List',
            'btn.theme_light': 'Light Mode', 'btn.theme_dark': 'Dark Mode',
            'btn.lock': 'Lock Map', 'btn.unlock': 'Unlock Map',
            'btn.fullscreen': 'Full Screen', 'btn.exit_fullscreen': 'Exit Fullscreen',
            'btn.users': 'Users', 'btn.calc': 'Attenuation Calculator', 'btn.vis': 'Visibility',
            'btn.measure': 'Ruler', 'btn.notifications': 'Notifications',
            'btn.search': 'Search', 'btn.lang': 'Language',
            'btn.anim_off': 'Disable Animation', 'btn.anim_on': 'Enable Animation',
            /* search */
            'search.placeholder': 'Search Lat, Lang, or name...',
            /* measure */
            'measure.title': 'Distance Measurement', 'measure.done': 'Done', 'measure.delete': 'Delete',
            'measure.mode_ruler': 'Ruler Mode', 'measure.mode_ruler_desc': 'Measure straight-line distance between points',
            'measure.mode_otdr': 'OTDR Mode', 'measure.mode_otdr_desc': 'Locate point by OTDR cable distance',
            'measure.hint': 'Click points on the map to start measuring. Right-click / Done to finish.',
            'measure.active_ruler': 'Ruler active — click points to measure distance',
            'measure.active_otdr': 'OTDR active — click Point 1 (Start) on the map',
            'measure.done_msg': 'Measurement complete', 'measure.need_pts': 'Click at least 2 points on the map',
            /* cable edit */
            'cable.title': 'Edit Cable Path', 'cable.color': 'Color', 'cable.thick': 'Thickness', 'cable.curve': 'Curve',
            'cable.hint': 'Click on map to add points along the path. Right-click / Enter = Done, Esc = Cancel, R = Straighten.',
            /* calculator */
            'calc.title': 'Attenuation Calculator', 'calc.ref_table': 'Reference Table', 'calc.close_ref': 'Close Reference Table', 'calc.flip_sign': 'Toggle sign + / -', 'calc.splitter_ratio': 'Splitter Ratio',
            'calc.splitter_plc': 'Splitter PLC', 'calc.simple': 'Simple Mode', 'calc.advanced': 'Advanced Mode',
            'calc.input_power': 'Input Power OLT/ODC (dBm)', 'calc.cable_length': 'Cable Length (m)',
            'calc.result': 'Calculator Result', 'calc.total_loss': 'Total Loss (dB)',
            'calc.signal_strong': 'Signal Too Strong', 'calc.signal_optimal': 'Excellent / Optimal',
            'calc.signal_warn': 'Warning / Threshold', 'calc.signal_bad': 'Risk of Poor Attenuation',
            /* detail card */
            'detail.device': 'DEVICE', 'detail.customer': 'CUSTOMER', 'detail.type': 'Type', 'detail.status': 'Status',
            'detail.paket': 'Package', 'detail.deadline': 'Deadline', 'detail.pppoe': 'PPPoE',
            'detail.serial': 'Serial', 'detail.mac': 'MAC', 'detail.ip': 'IP',
            'detail.mgmt_core': 'Management Core', 'detail.ya': 'Yes', 'detail.edit': 'Edit',
            'detail.map': 'Map', 'detail.coordinate': 'Coordinate',
            /* ONU table */
            'onu.title': 'ONU Table', 'onu.no': 'No', 'onu.name': 'Name', 'onu.type': 'Type',
            'onu.account': 'PPPoE Account', 'onu.ip': 'IP Address', 'onu.coord': 'Coordinate',
            'onu.htb': 'HTB', 'onu.odp': 'ODP', 'onu.olt': 'OLT',
            'onu.prev': 'Previous', 'onu.next': 'Next',
            'onu.loading': 'Loading ONU data...', 'onu.empty': 'No ONU data yet.', 'onu.no_data': 'No data.',
            'onu.search': 'Search name / type / account / IP / ODP / OLT...',
            /* visibility */
            'vis.title': 'Visibility', 'vis.router': 'Router', 'vis.odc': 'ODC', 'vis.odp': 'ODP',
            'vis.otb': 'OTB', 'vis.closure': 'Closure/JB', 'vis.onu_online': 'ONU Aktif', 'vis.onu_offline': 'ONU NonAktif',
            /* users */
            'users.title': 'User Settings', 'users.add': 'Add User', 'users.edit': 'Edit User', 'users.close': 'Close',
            'users.username': 'Username', 'users.password': 'Password', 'users.role': 'Role',
            'users.permissions': 'Permissions', 'users.name': 'Full Name', 'users.email': 'Email',
            'users.role_admin': 'ADMIN', 'users.role_noc': 'NOC', 'users.role_sales': 'SALES', 'users.role_tech': 'TECHNICIAN',
            'users.empty': 'No user accounts yet.', 'users.loading': 'Loading user data...',
            /* sync cards */
            'sync.mikrotik_title': 'Sync Mikrotik', 'sync.olt_title': 'Sync OLT', 'sync.genieacs_title': 'Sync GenieACS',
            'sync.mikrotik_ip': 'Mikrotik Local IP', 'sync.mikrotik_port': 'API Port',
            'sync.olt_ip': 'OLT IP', 'sync.olt_port': 'SSH Port', 'sync.olt_brand': 'OLT Brand',
            'sync.acs_url': 'GenieACS NBI URL',
            /* notifications */
            'notif.wa_title': 'WhatsApp Settings', 'notif.tg_title': 'Telegram Settings',
            'notif.wa_desc': 'API URL, key & recipient number', 'notif.tg_desc': 'Bot token & target chat ID',
            'notif.wa_active': 'Enable WhatsApp', 'notif.tg_active': 'Enable Telegram',
            'notif.bot_token': 'Bot Token', 'notif.chat_id': 'Target Chat ID',
            /* backup */
            'backup.title': 'Backup & Restore', 'backup.auto_gmail': 'Auto Backup to Gmail',
            'backup.email': 'Backup Email Recipient', 'backup.import_excel': 'Import Excel Data',
            'backup.export_excel': 'Export Excel Data', 'backup.import_kml': 'Import KML/KMZ',
            'backup.export_kmz': 'Export KMZ Data',
            /* device form */
            'device.add': 'Add Device', 'device.type': 'Type', 'device.type_select': '-- Select Type --',
            'device.parent': 'Parent', 'device.none': 'None', 'device.name': 'Name/Customer ID', 'device.name_device': 'Device Name',
            'device.mgmt_core': 'Enable Management Core',
            /* status bar */
            'status.pppoe_on': 'PPPoE Online:', 'status.pppoe_off': 'PPPoE Offline:',
            'status.onu_on': 'ONU Online:', 'status.onu_off': 'ONU Offline:',
            /* device categories */
            'cat.router': 'Router', 'cat.olt': 'OLT', 'cat.otb': 'OTB',
            'cat.odc': 'ODC', 'cat.odp': 'ODP', 'cat.htb': 'HTB',
            'cat.onu_pppoe': 'ONU (PPPoE)', 'cat.onu_hotspot': 'ONU (Hotspot)',
            /* common */
            'common.loading': 'Loading...', 'common.save': 'Save', 'common.cancel': 'Cancel',
            'common.delete': 'Delete', 'common.close': 'Close', 'common.ok': 'OK',
            'common.back_noc': 'Back to NOC Dashboard',
            'common.yes': 'Yes', 'common.no': 'No', 'common.confirm': 'Confirm',
            'common.saved': 'Saved', 'common.deleted': 'Deleted',
            'common.save_fail': 'Save failed', 'common.delete_fail': 'Delete failed',
            'common.connect_ok': 'Connection OK', 'common.connect_fail': 'Connection failed',
            'common.no_data': 'No data', 'common.location_found': 'Location found',
            'common.data_loaded': 'Data loaded', 'common.saving': 'Saving...',
            'confirm.delete_customer': 'Delete customer',
            'confirm.delete_router': 'Delete this router?', 'confirm.delete_olt': 'Delete this OLT?',
            /* toasts — toolbar */
            'toast.map_locked': 'Map locked', 'toast.map_unlocked': 'Map unlocked',
            'toast.light_mode': 'Light mode active', 'toast.dark_mode': 'Dark mode active',
            'toast.anim_off': 'Animation disabled', 'toast.anim_on': 'Animation enabled',
            'toast.exit_fullscreen': 'Exited Full Screen',
            /* toasts — measure/cable */
            'toast.cable_hint': 'Click on map to add cable path points along the road',
            'toast.cable_saved': 'Cable path saved', 'toast.cable_save_fail': 'Failed to save cable path',
            'toast.cable_cancelled': 'Cable path edit cancelled',
            'toast.need_points': 'Click at least 2 points on the map',
            /* toasts — users */
            'toast.user_load_fail': 'Failed to load users', 'toast.user_deleted': 'User deleted',
            'toast.user_saved': 'User saved',
            'toast.user_delete_fail': 'Failed to delete', 'toast.user_save_fail': 'Failed to save',
            /* toasts — search */
            'toast.type_first': 'Type coordinates or address first',
            'toast.searching': 'Searching',
            'toast.not_found': 'Location not found',
            'toast.search_fail': 'Search failed. Check connection.',
            'toast.no_coords': 'No coordinates for',
            /* toasts — router */
            'toast.fill_ip': 'Enter Mikrotik local IP', 'toast.fill_port': 'Enter API port',
            'toast.fill_user': 'Enter username', 'toast.fill_pass': 'Enter password',
            'toast.router_saved': 'Router saved', 'toast.router_save_fail': 'Failed to save router',
            'toast.save_first': 'Save router first to connect',
            'toast.router_synced': 'routers synced', 'toast.sync_fail': 'Sync failed',
            'toast.sync_all_fail': 'Failed to sync all routers',
            'toast.router_sync_fail': 'Router sync failed',
            'toast.router_deleted': 'Router deleted', 'toast.router_delete_fail': 'Failed to delete router',
            /* toasts — OLT */
            'toast.fill_olt_ip': 'Enter OLT IP', 'toast.fill_olt_port': 'Enter SSH port',
            'toast.olt_saved': 'OLT saved', 'toast.olt_save_fail': 'Failed to save OLT',
            'toast.save_olt_first': 'Save OLT first to connect',
            'toast.olt_synced': 'OLTs synced', 'toast.olt_sync_fail': 'OLT sync failed',
            'toast.olt_sync_all_fail': 'Failed to sync all OLTs',
            'toast.olt_deleted': 'OLT deleted', 'toast.olt_delete_fail': 'Failed to delete OLT',
            /* toasts — GenieACS */
            'toast.config_saved': 'Config saved', 'toast.config_save_fail': 'Failed to save config',
            'toast.genieacs_sync_fail': 'GenieACS sync failed',
            /* toasts — notifications */
            'toast.wa_saved': 'WhatsApp settings saved',
            'toast.tg_saved': 'Telegram settings saved',
            'toast.settings_save_fail': 'Failed to save settings',
            /* toasts — backup */
            'toast.config_save_ok': 'Configuration saved',
            'toast.config_save_err': 'Failed to save configuration',
            'toast.backup_sent': 'Backup sent', 'toast.backup_fail': 'Failed to send backup',
            'toast.restore_done': 'Restore complete', 'toast.restore_fail': 'Restore failed',
            'toast.import_done': 'Import complete', 'toast.import_fail': 'Import failed',
            /* toasts — device */
            'toast.device_saved': 'Device saved',
            'toast.no_coords_data': 'Data has no coordinate points on the map',
            'toast.goto': 'Going to:',
            'toast.searching_for': 'Searching',
            'toast.customer_delete_fail': 'Failed to delete customer',
            'toast.no_data_to_delete': 'No data to delete',
            'toast.data_delete_fail': 'Failed to delete data',
            'toast.status_change_fail': 'Failed to change status',
            'toast.device_delete_fail': 'Failed to delete device',
            'toast.export_fail': 'Failed to export data',
            'toast.copy_fail': 'Failed to copy',
            'toast.auto_sync_bg': 'Auto-sync processing in background...',
            'toast.auto_sync_done': 'Auto-sync complete —',
            'toast.no_sync_data': 'no data',
            /* innerHTML / loader messages */
            'loader.router': 'Loading router data...', 'loader.router_fail': 'Failed to load router list',
            'loader.olt': 'Loading OLT data...', 'loader.olt_fail': 'Failed to load OLT list',
            'loader.pppoe': 'Fetching PPPoE client data...', 'loader.pppoe_fail': 'Failed to load PPPoE client data',
            'loader.pppoe_empty': 'No active PPPoE clients.',
            'loader.pppoe_hint': 'Make sure router is saved &amp; synced.',
            'loader.hotspot': 'Fetching ONU Hotspot data...', 'loader.hotspot_fail': 'Failed to load ONU Hotspot data',
            'loader.hotspot_empty': 'No registered ONU Hotspot.',
            'loader.hotspot_hint': 'Make sure hotspot customers have linked ONUs.',
            'loader.onu': 'Loading ONU data...', 'loader.onu_fail': 'Failed to load data.',
            'loader.onu_empty': 'No ONU data yet.',
            'loader.devices': 'Loading device data...',
            'loader.users': 'Loading user data...',
            'loader.no_results': 'No search results',
            /* queue/hotspot status */
            'qstatus.loading': 'Loading...', 'qstatus.load_fail': 'Failed to load data',
            'qstatus.fail': 'Load failed',
            'qstatus.pppoe_active': 'PPPoE clients available',
            'qstatus.pppoe_empty': 'No active PPPoE clients.',
            'qstatus.pppoe_hint': 'Make sure router is saved & synced.',
            'qstatus.hotspot_empty': 'No registered ONU Hotspot.',
            'qstatus.hotspot_hint': 'Make sure hotspot customers have linked ONUs.',
            /* device categories */
            'cat.router_induk': 'Router',
            /* detail card labels */
            'detail.induk': 'Parent',
            /* ONU table info */
            'onu.info': 'Showing',
            'onu.info_of': 'of',
            'onu.info_data': 'entries',
            'onu.empty_msg': 'No ONU data yet.',
            /* generic */
            'msg.tidak_ada_hasil': 'No results found',
            'msg.no_data': 'No data.',
            'msg.gagal_load': 'Failed to load data.',
            'msg.aktifkan_animasi': 'Enable Animation', 'msg.matikan_animasi': 'Disable Animation',
            'msg.no_pppoe_active': 'No active PPPoE clients.',
            'msg.no_hotspot_registered': 'No registered ONU Hotspot.',
            'msg.pastikan_router_sync': 'Make sure router is saved &amp; synced.',
            'msg.pastikan_onu_linked': 'Make sure hotspot customers have linked ONUs.',
            'msg.mengambil_data_acs': 'Loading ACS info:',
            'loader.router_empty': 'No routers saved yet',
            'loader.olt_empty': 'No OLTs saved yet',
            'loader.sync_empty': 'No sync results yet',
            'loader.pppoe_search_empty': 'No search results',
            'loader.hotspot_search_empty': 'No search results',
            'loader.no_search': 'No results found',
            'msg.koneksi_ok': 'Connection OK',
            /* lock */
            'msg.lock_map': 'Lock Map', 'msg.unlock_map': 'Unlock Map',
            /* detail card location */
            'detail.tidak_diketahui': 'Unknown', 'detail.mencari_alamat': 'Looking up address...',
            /* detail card status */
            'detail.status_online': 'ONLINE', 'detail.status_offline': 'OFFLINE', 'detail.status_set': 'SET',
            /* device form labels */
            'device.kapasitas_port': 'Port Capacity', 'device.ip_address': 'IP Address',
            'device.user_pppoe': 'PPPoE User', 'device.jumlah_pon': 'PON Count',
            /* management core */
            'device.core_fields': 'Management Core',
            /* user empty */
            'users.empty': 'No user accounts yet.',
            /* device search */
            'device.tidak_ditemukan': 'Not found.', 'device.belum_ada_data': 'No data yet.',
            /* detail card badge */
            'detail.badge_pelanggan': 'CUSTOMER',
            /* sync button */
            'btn.syncing': 'Syncing...',
            /* log messages */
            'log.gagal_load_detail': 'Failed to load customer detail',
            'log.gagal_load_network': 'Failed to load customer detail (network)',
            'log.ping': 'Ping ...',
            'log.gagal_ping': 'Ping failed',
            'log.gagal_ping_net': 'Ping failed (network)',
            'log.no_acs_device': 'No ACS device connected for this customer',
            'log.loading_acs': 'Loading ACS info:',
            'log.gagal_ambil_acs': 'Failed to fetch ACS data',
            'log.gagal_ambil_acs_net': 'Failed to fetch ACS data (network)',
            'log.mengambil_ssid': 'Fetching SSID / WiFi ...',
            'log.gagal_ambil_ssid': 'Failed to fetch SSID',
            'log.gagal_ambil_ssid_net': 'Failed to fetch SSID (network)',
            'log.no_onu_reboot': 'Customer has no ONU (OLT) to reboot',
            'log.reboot_sending': 'Sending reboot command ...',
            'log.reboot_sent': 'Reboot command sent',
            'log.gagal_reboot': 'Reboot failed',
            'log.gagal_reboot_net': 'Reboot failed (network)',
            'log.duplikat_sending': 'Creating duplicate ...',
            'log.duplikat_hint': 'open Edit page to adjust',
            'log.gagal_duplikat': 'Duplicate failed',
            'log.gagal_duplikat_net': 'Duplicate failed (network)',
            /* calc reference table */
            'calc.ref_splitter': 'Passive Splitter PLC & Loss',
            'calc.ref_sp_loss': 'Loss (dB)',
            'calc.ref_component': 'Standard Component Loss',
            'calc.ref_splice': 'Splicing Loss', 'calc.ref_splice_val': '0.03 dB / point',
            'calc.ref_kabel': 'Fiber Cable Loss', 'calc.ref_kabel_val': '0.30 dB / km',
            'calc.ref_fc': 'Fast Connector', 'calc.ref_fc_val': '0.30 dB / pc',
            'calc.ref_connector': 'Connector / Adapter', 'calc.ref_connector_val': '0.30 dB / pc',
            'calc.ref_pigtail': 'Pigtail / Patchcord', 'calc.ref_pigtail_val': '0.30 dB / pc',
            'calc.flip_hint': 'Toggle + / - sign', 'calc.placeholder': 'e.g. 9 or -9',
            'calc.input_hint': 'Input power in dBm. ± button to toggle sign.',
            'calc.no_ratio': 'No Ratio', 'calc.no_plc': 'No PLC',
            'calc.konektor': 'Connector Count', 'calc.konektor_hint': '~0.3 dB / PC',
            'calc.tanpa_konektor': 'No connector',
            'calc.splice': 'Splice connections', 'calc.splice_hint': '~0.1 dB / Point',
            'calc.jarak_kabel': 'Cable Length', 'calc.jarak_hint': 'Est. cable Loss ~0.35 dB / KM',
            'calc.result_title': 'Calculator Result',
            'calc.power_received': 'Power received by ONT/Modem',
            'calc.pass_through': 'Remaining signal pass-through to next ODP:',
            'calc.loss_splitter': 'Splitter Loss', 'calc.loss_kabel': 'Cable Fiber Loss',
            'calc.loss_splice': 'Splice connections', 'calc.loss_konektor': 'Connectors',
            'calc.total_loss_detail': 'Total Optical Loss',
            'calc.note_optimal': 'Optimal ONT range: -15 dBm to -24 dBm. Above -27 dBm may cause LOS.',
            /* visibility panel */
            'vis.fitur_peta': 'Map Features',
            'vis.router_induk': 'Router', 'vis.odc_distribusi': 'ODC',
            'vis.odp_point': 'ODP', 'vis.otb_pusat': 'OTB',
            'vis.tampilkan_teks_onu': 'Show ONU Text',
            'vis.tampilkan_nama': 'Show Name',
            'vis.tampilkan_pppoe': 'Show PPPoE',
            'vis.sembunyikan_teks': 'Hide Text',
            'vis.jalur_kabel': 'Cable Path',
            'vis.sembunyikan_tombol': 'Hide Buttons',
            'vis.onu_teks': 'Check to show elements, uncheck to hide.',
            /* user form */
            'user.kosongkan_hint': 'Leave blank to keep unchanged',
            'user.tampilkan_sandi': 'Show password',
            'user.hak_akses': 'Permissions (for Sales Role)',
            'user.perm_edit_map': 'Edit Map (Add/Delete/Change)',
            'user.perm_sync_mk': 'Can sync Mikrotik',
            'user.perm_sync_olt': 'Can sync OLT',
            'user.perm_sync_acs': 'Can sync GenieACS',
            'user.perm_ganti_wifi': 'Can change WiFi',
            'user.perm_import_export': 'Can Import/Export Excel',
            /* menu / fab */
            'fab.menu_utama': 'Main Menu', 'fab.peta': 'Map', 'fab.satelit': 'Satellite',
            'fab.gaya_peta': 'Map Style',
            /* sync card form labels */
            'sync.konek': 'Connect', 'sync.simpan_config': 'Save Config', 'sync.sync_acs': 'Syncing with ACS',
            'sync.sync_all_routes': 'Sync All Saved Routes',
            'sync.sync_all_olt': 'Sync All Saved OLT',
            'sync.user_pppoe': 'PPPoE User',
            /* notification form */
            'notif.wa_url': 'WhatsApp API URL', 'notif.wa_api_key': 'API Key',
            'notif.wa_sender': 'Sender Number', 'notif.wa_dest': 'Destination Number',
            /* backup form */
            'backup.auto_gmail_section': 'Auto Backup to Gmail', 'backup.harian': 'Daily',
            'backup.email_label': 'Backup Email Recipient', 'backup.jam_label': 'Backup Time',
            'backup.simpan_backup': 'Save Backup', 'backup.kirim_sekarang': 'Send Now',
            'backup.restore_section': 'Restore JSON File',
            'backup.restore_db': 'Restore database.json', 'backup.restore_routers': 'Restore Routers.json',
            'backup.excel_section': 'Excel Data Backup & Restore',
            'backup.kmz_section': 'Google Earth Sync (KMZ)',
            /* device form */
            'device.placeholder': 'e.g. ODP Gang 5 / OLT MA5800',
            'device.edit': 'Edit Device',
            'device.add_onu': 'Add Device — ONU',
            /* browse */
            'browse.cari': 'Search data...', 'browse.hapus_semua': 'Delete All',
            'browse.jelajahi': 'Browse Data', 'browse.kembali': 'Back',
            'browse.daftar': 'List',
            'browse.perangkat': 'devices',
            'browse.open_settings': 'Open settings', 'browse.goto_point': 'Go to point on map',
            /* ONU table toolbar */
            'onu.print_pdf': 'Print PDF', 'onu.semua': 'All', 'onu.pppp_only': 'PPPoE Only', 'onu.hotspot_only': 'Hotspot Only',
            'onu.sebelumnya': 'Previous', 'onu.selanjutnya': 'Next',
            /* detail card */
            'detail.seret': 'Drag to move card',
            'detail.hapus_btn': 'Delete', 'detail.edit_btn': 'Edit', 'detail.status_btn': 'Status',
            'detail.edit_kabel': 'Edit Cable',
            'detail.detail_label': 'Detail', 'detail.brand': 'Brand', 'detail.model': 'Model',
            'detail.kapasitas': 'Capacity',
            'detail.mgmt_core_label': 'Management Core',
            'detail.open_settings': 'Open settings', 'detail.goto_peta': 'Go to point on map',
            /* ODC card */
            'odc.title': 'ODC Device', 'odc.type': 'Type', 'odc.port_usage': 'Port Usage',
            'odc.sisa': 'Remaining', 'odc.onu_per_jalur': 'Total ONU per Port (Path)',
            'odc.mgmt_core': 'Management Core', 'odc.mgmt_aktif': 'Active', 'odc.mgmt_nonaktif': 'Inactive',
            'odc.uptime': 'Uptime', 'odc.loading': 'Loading ODC data...', 'odc.distance': 'Distance',
            'odc.salin': 'Copy', 'odc.maps': 'Maps', 'odc.wa': 'WA', 'odc.edit': 'Edit', 'odc.duplikat': 'Duplicate',
            /* customer detail */
            'cust.tipe': 'Type', 'cust.aktif': 'Active', 'cust.ditangguhkan': 'Suspended',
            'cust.paket': 'Package', 'cust.odp': 'ODP', 'cust.port_odp': 'ODP Port',
            'cust.port_olt': 'OLT Port', 'cust.tenggat': 'Deadline', 'cust.serial': 'Serial', 'cust.mac': 'MAC',
            'cust.onu_sesi': 'ONU & Sessions', 'cust.ip_publik': 'Public IP',
            'cust.mati': 'Down', 'cust.memuat': 'LOADING...',
            'cust.lokasi': 'Location', 'cust.koordinat': 'Coordinates',
            /* customer action buttons */
            'cust.btn_ping': 'PING', 'cust.btn_status': 'Status', 'cust.btn_acs': 'ACS',
            'cust.btn_wifi': 'Wifi', 'cust.btn_reboot': 'Reboot', 'cust.btn_salin': 'Copy',
            'cust.btn_gmaps': 'Open in Google Maps', 'cust.btn_wa': 'Chat WhatsApp',
            'cust.btn_edit': 'Edit customer', 'cust.btn_duplikat': 'Duplicate customer',
            /* ping/acs/wifi results */
            'result.host': 'Host:', 'result.status': 'Status:', 'result.latency': 'Latency:',
            'result.jitter': 'Jitter:', 'result.pkt_loss': 'Packet loss:',
            'result.device': 'Device:', 'result.pabrikan': 'Manufacturer:', 'result.produk': 'Product:',
            'result.firmware': 'Firmware:', 'result.mode': 'Mode:',
            'result.ssid': 'SSID:', 'result.wifi': 'WiFi:', 'result.channel': 'Channel:',
            /* copy text */
            'copy.status': 'Status:', 'copy.ip': 'IP:', 'copy.port_olt': 'OLT Port:',
            'copy.port_odp': 'ODP Port:', 'copy.paket': 'Package:', 'copy.lokasi': 'Location:',
            'copy.koordinat': 'Coordinates:', 'copy.maps': 'Maps:', 'copy.wa': 'WA:', 'copy.edit': 'Edit:',
            'copy.ringkasan': 'Summary',
            /* map popup */
            'popup.online': '● Online', 'popup.offline': '● Offline',
            /* measure result labels */
            'measure.otdr_title': 'OTDR — Estimation',
            'measure.jarak_lurus': 'Straight-line distance', 'measure.panjang_kabel': 'Cable length',
            'measure.redaman_total': 'Total attenuation', 'measure.ruas_titik': 'Segment / Point',
            'measure.total_jarak': 'Total distance', 'measure.titik': 'Points',
            'measure.otdr_hint': 'Estimation: cable × attenuation dB/km + connections.',
            'measure.otdr_hint2': 'Click points along cable path. Done / right-click to finish.',
            'measure.result_hint_done': 'Measurement complete. Click Delete to reset.',
            'measure.result_hint_active': 'Click points on the map to add segments.',
            'measure.pengukuran_jarak': 'Distance Measurement',
            /* cable edit */
            'cable.titik': 'Points', 'cable.panjang_jalur': 'Path length',
            /* search */
            'search.goto_label': 'Coordinates:',
            /* genieacs summary */
            'acs.online': 'Online', 'acs.offline': 'Offline',
            'acs.device_aktif': 'GenieACS device active', 'acs.device_tidak_aktif': 'GenieACS device inactive',
            'acs.total_device': 'Total devices', 'acs.onu_connected': 'ONUs connected',
            /* confirm dialogs */
            'confirm.hapus_user': 'Delete this user?',
            'confirm.hapus_router': 'Delete this router?',
            'confirm.hapus_olt': 'Delete this OLT?',
            'confirm.hapus_pelanggan': 'Delete customer',
            'confirm.hapus_semua': 'Delete all',
            'confirm.data_in': 'data?',
            'confirm.hapus_perangkat': 'Delete this device?',
            'confirm.reboot_onu': 'Reboot ONU',
            'confirm.duplikat_pelanggan': 'Duplicate customer',
            /* status bar dynamic */
            'status.user_pppoe': 'PPPoE users', 'status.onu': 'ONU',
            'status.sync_ok': 'Sync OK', 'status.konek_ok': 'Connected',
            'status.online': 'Online', 'status.offline': 'Offline',
            'status.genieacs_active': 'GenieACS device active', 'status.genieacs_inactive': 'GenieACS device inactive',
            'status.total_device': 'Total devices', 'status.onu_tersambung': 'ONU connected',
            /* queue item */
            'queue.sn': 'SN:', 'queue.olt': 'OLT:', 'queue.odp': 'ODP:', 'queue.add': 'ADD',
            'queue.search': 'Search name / SN / OLT / IP...',
            'hotspot.search': 'Search name / serial...',
            /* device validation */
            'device.pilih_type': 'Select device type first',
            'device.nama_wajib': 'Device name is required',
            'device.menysimpan': 'Saving...', 'device.tersimpan': 'Saved',
            'device.gagal_simpan': 'Save failed', 'device.gagal_menyimpan': 'Save failed',
            'device.daftar_perangkat': 'Device List', 'device.perangkat': 'devices',
            'device.jelajahi': 'Browse Data', 'device.daftar': 'List',
            'device.kembali': 'Back',
            /* sync status messages */
            'sync.menyimpan': 'Saving...', 'sync.gagal_simpan': 'Save failed',
            'sync.tersimpan': 'Saved', 'sync.menghubungkan': 'Connecting...',
            'sync.konek_gagal': 'Connection failed', 'sync.menyinkronkan_semua': 'Syncing all...',
            'sync.gagal': 'Failed', 'sync.gagal_sync': 'Sync failed', 'sync.sync_gagal': 'Sync failed',
            'sync.menghapus': 'Deleting...', 'sync.gagal_hapus': 'Delete failed',
            'sync.router_dihapus': 'Router deleted', 'sync.olt_dihapus': 'OLT deleted',
            'sync.config_tersimpan': 'Config saved',
            'sync.menyinkronkan': 'Syncing...', 'sync.menyiapkan_backup': 'Preparing backup & sending...',
            'sync.terkirim': 'Sent', 'sync.gagal_kirim': 'Send failed',
            'sync.memulihkan': 'Restoring...', 'sync.restore_selesai': 'Restore complete',
            'sync.gagal_restore': 'Restore failed', 'sync.mengimpor_excel': 'Importing Excel data...',
            'sync.gagal_import': 'Import failed', 'sync.import_selesai': 'Import complete',
            'sync.mengimpor_kmz': 'Importing KML/KMZ...',
            'sync.gagal_simpan_pengaturan': 'Failed to save settings',
            /* tag marker */
            'tag.silahkan_geser': 'Drag to position marker',
            /* queue hotspot loader */
            'loader.pppp_client': 'Loading PPPoE clients...', 'loader.hotspot_onu': 'Loading ONU Hotspot...',
        }
    };

    function ftthT(key, fallback) {
        var dict = FTTH_I18N[FTTH_LANG] || FTTH_I18N.id;
        return dict[key] || FTTH_I18N.id[key] || fallback || key;
    }

    function ftthToggleLang() {
        FTTH_LANG = FTTH_LANG === 'id' ? 'en' : 'id';
        localStorage.setItem('ftth_lang', FTTH_LANG);
        document.getElementById('ftthLangLabel').textContent = FTTH_LANG === 'id' ? 'EN' : 'ID';
        ftthApplyLang();
        ftthToast(FTTH_LANG === 'id' ? 'Bahasa Indonesia' : 'English', 'ok');
    }

    function ftthApplyLang() {
        document.querySelectorAll('[data-i18n]').forEach(function(el) {
            var k = el.getAttribute('data-i18n');
            var t = ftthT(k);
            if (el.tagName === 'INPUT' && el.hasAttribute('placeholder')) {
                el.placeholder = t;
            } else if (el.tagName === 'OPTION') {
                el.textContent = t;
            } else {
                if (el.hasAttribute('title')) el.title = t;
                if (el.tagName === 'STRONG' || el.tagName === 'SMALL' || el.tagName === 'SPAN') {
                    el.textContent = t;
                } else {
                    for (var i = 0; i < el.childNodes.length; i++) {
                        if (el.childNodes[i].nodeType === 3 && el.childNodes[i].textContent.trim()) {
                            el.childNodes[i].textContent = ' ' + t;
                        }
                    }
                }
            }
        });
        /* Re-render dynamic content that uses ftthT() internally */
        try { if (typeof renderQueue === 'function') renderQueue(); } catch(e) {}
        try { if (typeof renderHotspot === 'function') renderHotspot(); } catch(e) {}
        try { if (typeof ftthRenderCategories === 'function') ftthRenderCategories(); } catch(e) {}
        try { if (typeof ftthBrowseRender === 'function') ftthBrowseRender(); } catch(e) {}
        try { if (typeof ftthOnuTableFilter === 'function') ftthOnuTableFilter(); } catch(e) {}
        try { if (typeof loadMapMarkers === 'function') loadMapMarkers(); } catch(e) {}
        /* Re-show active detail card if open */
        try {
            var dc = document.getElementById('ftthDetailCard');
            if (dc && !dc.hidden && ftthActiveMarker) {
                var d = ftthActiveMarker._ftthData || ftthActiveMarker._ftthDevice;
                if (d) ftthShowDetail(ftthActiveMarker, d);
            }
        } catch(e) {}
        /* Re-show calculator if open */
        try {
            var cc = document.getElementById('ftthCalcCard');
            if (cc && cc.style.display !== 'none') ftthCalcRebuild();
        } catch(e) {}
        /* Re-translate dynamic-title buttons */
        try { if (typeof ftthUpdateThemeBtn === 'function') ftthUpdateThemeBtn(); } catch(e) {}
        try {
            var lockBtn = document.getElementById('ftthLockBtn');
            if (lockBtn) lockBtn.title = (typeof mapLocked !== 'undefined' && mapLocked) ? ftthT('msg.unlock_map') : ftthT('msg.lock_map');
        } catch(e) {}
        try {
            var animBtn = document.getElementById('ftthAnimBtn');
            if (animBtn) animBtn.title = animBtn.classList.contains('active') ? ftthT('btn.anim_off') : ftthT('btn.anim_on');
        } catch(e) {}
        try {
            var fsBtn = document.getElementById('ftthFullscreenBtn');
            if (fsBtn) fsBtn.title = document.fullscreenElement ? ftthT('btn.exit_fullscreen') : ftthT('btn.fullscreen');
        } catch(e) {}
        /* Re-translate notification dropdown text */
        try { document.querySelectorAll('.ftth-notif-item strong[data-i18n], .ftth-notif-item small[data-i18n]').forEach(function(el) {
            el.textContent = ftthT(el.getAttribute('data-i18n'));
        }); } catch(e) {}
    }
    (function() {
        var map = L.map('ftth-map', {
            center: [-6.4857, 106.0152],
            zoom: 19,
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

        /* Permanent default view for all visitors (set from the agreed reference point) */
        window.ftthMap = map;

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

        /* ── Collapsible section "Sembunyikan Tombol" ── */
        window.ftthToggleVisSection = function(headerId, bodyId) {
            var body = document.getElementById(bodyId);
            var header = document.getElementById(headerId);
            if (!body || !header) return;
            var willShow = body.hasAttribute('hidden');
            if (willShow) body.removeAttribute('hidden');
            else body.setAttribute('hidden', '');
            header.classList.toggle('open', willShow);
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
                btn.setAttribute('title', locked ? ftthT('msg.unlock_map') : ftthT('msg.lock_map'));
            }
            if (icon) {
                icon.classList.toggle('fa-lock', !locked);
                icon.classList.toggle('fa-unlock', locked);
            }
            ftthToast(locked ? ftthT('toast.map_locked') : ftthT('toast.map_unlocked'), 'info');
        }

        window.ftthToggleLock = function() { setMapLock(!mapLocked); };

        /* ── Toggle mode light/dark: button fitur & card ── */
        window.ftthUpdateThemeBtn = function() {
            var light = document.body.classList.contains('ftth-light');
            var icon = document.getElementById('ftthThemeIcon');
            var btn = document.getElementById('ftthThemeBtn');
            if (icon) icon.className = light ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
            if (btn) btn.title = light ? ftthT('btn.theme_dark') : ftthT('btn.theme_light');
        };
        window.ftthToggleTheme = function() {
            document.body.classList.toggle('ftth-light');
            try { localStorage.setItem('ftth_light', document.body.classList.contains('ftth-light') ? '1' : '0'); } catch (e) {}
            window.ftthUpdateThemeBtn();
            ftthToast(document.body.classList.contains('ftth-light') ? ftthT('toast.light_mode') : ftthT('toast.dark_mode'), 'info');
        };
        (function() {
            try { if (localStorage.getItem('ftth_light') === '1') document.body.classList.add('ftth-light'); } catch (e) {}
            window.ftthUpdateThemeBtn();
        })();


        /* ── Toggle animasi: hidupkan/matikan semua animasi peta ── */
        window.ftthToggleAnim = function() {
            var off = document.body.classList.toggle('ftth-anim-off');
            var btn = document.getElementById('ftthAnimBtn');
            var icon = document.getElementById('ftthAnimIcon');
            if (btn) {
                btn.classList.toggle('active', !off);
                btn.setAttribute('title', off ? ftthT('msg.aktifkan_animasi') : ftthT('msg.matikan_animasi'));
            }
            if (icon) {
                icon.classList.toggle('fa-circle-play', !off);
                icon.classList.toggle('fa-circle-pause', off);
            }
            ftthToast(off ? ftthT('toast.anim_off') : ftthT('toast.anim_on'), 'info');
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
                if (btn) btn.setAttribute('title', ftthT('btn.exit_fullscreen'));
            } else {
                body.classList.remove('ftth-fs-active');
                map.flyTo(fsState.center, fsState.zoom, { duration: 0.8 });
                if (icon) { icon.classList.remove('fa-compress'); icon.classList.add('fa-expand'); }
                if (btn) btn.setAttribute('title', ftthT('btn.fullscreen'));
                fsState.active = false;
                ftthToast(ftthT('toast.exit_fullscreen'), 'ok');
            }
        };

        /* ── Penggaris Ukur: dropdown mode (Ukur / OTDR) + pengukuran ── */
        var measureMenuOpen = false;

        function measureBtnState() {
            var btn = document.getElementById('ftthMeasureBtn');
            if (btn) btn.classList.toggle('active', measureMenuOpen || measure.active);
        }

        function ftthForceCloseMeasure() {
            if (typeof measureMenuOpen !== 'undefined' && measureMenuOpen) {
                measureMenuOpen = false;
                var mm = document.getElementById('ftthMeasureMenu');
                if (mm) mm.classList.remove('open');
                if (typeof measureBtnState === 'function') measureBtnState();
            }
            if (typeof measure !== 'undefined' && (measure.active || measure.mode)) {
                ftthMeasureClose();
            }
        }
        function ftthForceCloseNotif() {
            if (typeof notifMenuOpen !== 'undefined' && notifMenuOpen) {
                notifMenuOpen = false;
                var nm = document.getElementById('ftthNotifMenu');
                if (nm) nm.classList.remove('open');
                if (typeof notifBtnState === 'function') notifBtnState();
            }
        }
        function ftthForceCloseQueue() {
            var qm = document.getElementById('ftthQueueMenu');
            if (qm) qm.hidden = true;
        }

        window.ftthToggleMeasureMenu = function() {
            ftthCloseAllCards();
            ftthForceCloseQueue();
            ftthForceCloseNotif();
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
            ftthCloseAllCards();
            ftthForceCloseQueue();
            ftthForceCloseMeasure();
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

        var measure = { active: false, finished: false, mode: null, points: [], line: null, ghost: null, markers: [], labels: [], fault: [], otdrVal: '', otdrMsg: '', otdrCls: '', otdrA: null, otdrB: null, otdrRoutePts: null, otdrTotal: 0, otdrFaultPos: null };

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
            measure.fault.forEach(function(l) { map.removeLayer(l); });
            measure.markers = [];
            measure.labels = [];
            measure.fault = [];
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

            var body, hint, title, hintCls = '';
            if (isOtdr) {
                title = 'Mode OTDR';
                var A = measure.otdrA;
                var B = measure.otdrB;
                var lbl = function(d) { return d ? escapeHtml(String(d.label || d.name || ('Perangkat #' + d.id))) : '-'; };
                body =
                    '<div class="fm-row"><span>Titik 1 (Start)</span><b>' + lbl(A) + '</b></div>' +
                    '<div class="fm-row"><span>Titik 2 (End)</span><b>' + lbl(B) + '</b></div>' +
                    '<div class="ftth-otdr-route-box"><span class="lbl">Jarak Rute</span><b class="val">' + ((A && B && measure.otdrTotal > 0) ? fmtMeters(measure.otdrTotal) : '-') + '</b></div>' +
                    (measure.otdrFaultPos ? '<div class="ftth-otdr-fault-coord">Tikor Putus: ' + measure.otdrFaultPos[0].toFixed(6) + ', ' + measure.otdrFaultPos[1].toFixed(6) + '</div>' : '') +
                    '<div class="ftth-otdr-input-row">' +
                        '<input type="text" id="otdrDistInput" inputmode="decimal" autocomplete="off" placeholder="Jarak rute/kabel (m / km)" value="' + (measure.otdrVal || '') + '">' +
                        '<button type="button" class="ftth-measure-act ftth-measure-otdr-act ftth-otdr-cek" onclick="ftthOtdrCek()"><i class="fa-solid fa-magnifying-glass"></i> Cek</button>' +
                    '</div>' +
                    '<button type="button" class="ftth-otdr-reset" onclick="ftthOtdrReset()">Reset Titik Pilihan</button>';
                if (measure.otdrMsg) {
                    hint = measure.otdrMsg;
                    hintCls = measure.otdrCls === 'ok' ? ' ftth-measure-hint-ok' : ' ftth-measure-hint-warn';
                } else {
                    hint = !A ? 'Klik perangkat START di peta.'
                        : !B ? 'Klik perangkat END berikutnya yang terhubung.'
                        : 'Masukkan jarak dari alat OTDR (boleh m atau km, mis. 350 / 1.2km), lalu klik Cek.';
                }
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
            var hintEl = document.getElementById('ftthMeasureHint');
            hintEl.textContent = hint;
            hintEl.className = 'ftth-measure-result-hint' + hintCls;
            var sBtn = document.getElementById('ftthMeasureSelesaiBtn');
            if (sBtn) sBtn.classList.toggle('ftth-measure-otdr-act', isOtdr);
            if (isOtdr) {
                var oi = document.getElementById('otdrDistInput');
                if (oi) oi.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); ftthOtdrCek(); } });
            }
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
            measure.otdrVal = '';
            measure.otdrMsg = '';
            measure.otdrCls = '';
            measure.otdrA = null;
            measure.otdrB = null;
            measure.otdrRoutePts = null;
            measure.otdrTotal = 0;
            measure.otdrFaultPos = null;
            map.doubleClickZoom.disable();
            if (mode === 'otdr') {
                ftthToast(ftthT('measure.active_otdr'), 'info');
                measureRenderResult();
                return;
            }
            measureDraw();
            ftthToast(mode === 'otdr' ? ftthT('measure.active_otdr') : ftthT('measure.active_ruler'), 'info');
        };

        window.ftthMeasureSelesai = function() {
            if (!measure.active || measure.finished) return;
            if (measure.points.length < 2) { ftthToast(ftthT('measure.need_pts'), 'warn'); return; }
            measure.active = false;
            measure.finished = true;
            if (measure.ghost) { map.removeLayer(measure.ghost); measure.ghost = null; }
            measureRenderResult();
            ftthToast(ftthT('measure.done_msg'), 'ok');
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
            measure.otdrVal = '';
            measure.otdrMsg = '';
            measure.otdrCls = '';
            measure.otdrA = null;
            measure.otdrB = null;
            measure.otdrRoutePts = null;
            measure.otdrTotal = 0;
            measure.otdrFaultPos = null;
            measureClearLayers();
            var card = document.getElementById('ftthMeasureResult');
            if (card) card.style.display = 'none';
            if (!mapLocked) map.doubleClickZoom.enable();
            measureBtnState();
        };

        /* ── Mode OTDR: pilih 2 perangkat, ukur sepanjang jalur kabel, cek titik ── */
        function ftthOtdrRouteFor(devId) {
            var found = null;
            if (typeof cableLayer !== 'undefined' && cableLayer) {
                cableLayer.eachLayer(function(l) {
                    if (!found && l._cableMarkerId === devId && l.getLatLngs) found = l;
                });
            }
            if (!found) return null;

            return found.getLatLngs().map(function(ll) { return [ll.lat, ll.lng]; });
        }

        function ftthOtdrRedraw() {
            measureClearLayers();
            var A = measure.otdrA;
            var B = measure.otdrB;
            if (A) measure.markers.push(L.marker([Number(A.lat), Number(A.lon)], { icon: L.divIcon({ className: 'ftth-measure-point otdr', iconSize: [12, 12], iconAnchor: [6, 6] }), interactive: false }).addTo(map));
            if (B) measure.markers.push(L.marker([Number(B.lat), Number(B.lon)], { icon: L.divIcon({ className: 'ftth-measure-point otdr', iconSize: [12, 12], iconAnchor: [6, 6] }), interactive: false }).addTo(map));
            if (measure.otdrRoutePts && measure.otdrRoutePts.length > 1) {
                measure.line = L.polyline(measure.otdrRoutePts, { color: '#38bdf8', weight: 3.5, opacity: 0.95 }).addTo(map);
            }
            measureRenderResult();
        }

        window.ftthOtdrPickDevice = function(m) {
            if (measure.mode !== 'otdr' || !measure.active || measure.finished) return;
            if (!measure.otdrA) {
                measure.otdrA = m;
                measure.otdrMsg = '';
                measure.otdrCls = '';
                measure.fault.forEach(function(l) { map.removeLayer(l); });
                measure.fault = [];
                measure.otdrVal = '';
                ftthOtdrRedraw();

                return;
            }
            if (!measure.otdrB) {
                if (measure.otdrA.id === m.id) { ftthToast('Titik 2 harus perangkat yang lain', 'warn'); return; }
                var route = ftthOtdrRouteFor(m.id);
                if (!route || route.length < 2) {
                    ftthToast('Tidak ada kabel pada perangkat itu — pilih perangkat terhubung berikutnya', 'warn');

                    return;
                }
                if (map.distance(route[0], [Number(measure.otdrA.lat), Number(measure.otdrA.lon)]) > 5) {
                    ftthToast('Perangkat itu tidak terhubung langsung ke Titik 1 — urutan: dari asal kabel ke ujung kabel', 'warn');

                    return;
                }
                measure.otdrB = m;
                measure.otdrRoutePts = route;
                var tot = 0;
                for (var i = 1; i < route.length; i++) tot += map.distance(route[i - 1], route[i]);
                measure.otdrTotal = tot;
                measure.otdrMsg = '';
                measure.otdrCls = '';
                measure.otdrVal = '';
                ftthOtdrRedraw();
            }
        };

        window.ftthOtdrCek = function() {
            if (measure.mode !== 'otdr') return;
            if (!(measure.otdrA && measure.otdrB) || !(measure.otdrRoutePts && measure.otdrRoutePts.length > 1)) {
                ftthToast('Lengkapi Titik 1 & Titik 2 terlebih dahulu', 'warn');

                return;
            }
            var inp = document.getElementById('otdrDistInput');
            var raw = inp ? String(inp.value).trim().replace(',', '.') : '';
            var mt = raw.match(/^(\d+(?:\.\d+)?)\s*(km|m)?$/i);
            if (!mt) { ftthToast('Isi jarak rute/kabel — contoh: 350 atau 1.2km', 'warn'); return; }
            var v = parseFloat(mt[1]);
            if (mt[2] && mt[2].toLowerCase() === 'km') v = v * 1000;
            measure.otdrVal = inp.value;

            var pts = measure.otdrRoutePts;
            var total = measure.otdrTotal;
            measure.fault.forEach(function(l) { map.removeLayer(l); });
            measure.fault = [];

            if (v > total) {
                measure.fault.push(L.marker(pts[pts.length - 1], { icon: L.divIcon({ className: 'ftth-measure-point otdr', iconSize: [12, 12], iconAnchor: [6, 6] }), interactive: false }).addTo(map));
                measure.otdrMsg = 'Jarak ' + fmtMeters(v) + ' melebihi Jarak Rute (' + fmtMeters(total) + ') — periksa kembali.';
                measure.otdrCls = 'warn';
                measure.otdrFaultPos = null;
            } else {
                /* Interpolasi posisi di sepanjang jalur kabel asli (mengikuti belokan) */
                var acc = 0;
                var pos = pts[0];
                for (var i = 1; i < pts.length; i++) {
                    var d = map.distance(pts[i - 1], pts[i]);
                    if (v <= acc + d) {
                        var f = d > 0 ? (v - acc) / d : 0;
                        pos = [pts[i - 1][0] + (pts[i][0] - pts[i - 1][0]) * f, pts[i - 1][1] + (pts[i][1] - pts[i - 1][1]) * f];
                        break;
                    }
                    acc += d;
                    pos = pts[i];
                }
                measure.fault.push(L.marker(pos, { icon: L.divIcon({ className: 'ftth-measure-point ftth-otdr-fault-point', iconSize: [12, 12], iconAnchor: [6, 6] }), interactive: false }).addTo(map));
                measure.fault.push(L.marker(pos, { icon: L.divIcon({ className: 'ftth-otdr-fault-label', html: '≈ ' + fmtMeters(v), iconSize: null }), interactive: false }).addTo(map));
                measure.otdrFaultPos = pos;
                measure.otdrMsg = 'Perkiraan titik: ' + fmtMeters(v) + ' dari Titik 1 (' + Math.round((total > 0 ? v / total : 0) * 100) + '% rute) · sisa ' + fmtMeters(total - v) + ' ke Titik 2.';
                measure.otdrCls = 'ok';
            }
            measureRenderResult();
        };

        window.ftthOtdrReset = function() {
            if (measure.mode !== 'otdr') return;
            measure.points = [];
            measure.active = true;
            measure.finished = false;
            measure.otdrVal = '';
            measure.otdrMsg = '';
            measure.otdrCls = '';
            measure.otdrA = null;
            measure.otdrB = null;
            measure.otdrRoutePts = null;
            measure.otdrTotal = 0;
            measure.otdrFaultPos = null;
            measureClearLayers();
            measureRenderResult();
            ftthToast('Titik pilihan direset', 'info');
        };

        map.on('click', function(e) {
            if (!measure.active || measure.finished) return;
            /* Mode OTDR hanya menerima klik pada perangkat (marker), bukan peta kosong */
            if (measure.mode === 'otdr') return;
            if (measure.mode === 'otdr' && measure.points.length >= 2) return;
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
            if (measure.mode === 'otdr') {
                if (measure.ghost) { map.removeLayer(measure.ghost); measure.ghost = null; }

                return;
            }
            if (measure.ghost) map.removeLayer(measure.ghost);
            var pts = measure.points.slice();
            pts.push([e.latlng.lat, e.latlng.lng]);
            measure.ghost = L.polyline(pts, { color: measureColor(), weight: 2, dashArray: '3 6', opacity: 0.6 }).addTo(map);
        });

        map.on('dblclick', function() { if (measure.mode === 'otdr') return; ftthMeasureSelesai(); });
        map.on('contextmenu', function(e) {
            if (!measure.active || measure.finished) return;
            if (measure.mode === 'otdr') return;
            L.DomEvent.stop(e);
            ftthMeasureSelesai();
        });

        /* ── Edit Jalur Kabel: klik titik di peta agar kabel mengikuti jalan ── */
        function ftthCatmullRom(pts, seg) {
            if (!pts || pts.length < 3) return pts.slice();
            var out = [];
            seg = seg || 12;
            for (var i = 0; i < pts.length - 1; i++) {
                var p0 = pts[i - 1] || pts[i];
                var p1 = pts[i];
                var p2 = pts[i + 1];
                var p3 = pts[i + 2] || p2;
                for (var s = 0; s < seg; s++) {
                    var t = s / seg, t2 = t * t, t3 = t2 * t;
                    var x = 0.5 * ((2 * p1[0]) + (-p0[0] + p2[0]) * t + (2 * p0[0] - 5 * p1[0] + 4 * p2[0] - p3[0]) * t2 + (-p0[0] + 3 * p1[0] - 3 * p2[0] + p3[0]) * t3);
                    var y = 0.5 * ((2 * p1[1]) + (-p0[1] + p2[1]) * t + (2 * p0[1] - 5 * p1[1] + 4 * p2[1] - p3[1]) * t2 + (-p0[1] + 3 * p1[1] - 3 * p2[1] + p3[1]) * t3);
                    out.push([x, y]);
                }
            }
            out.push(pts[pts.length - 1]);
            return out;
        }

        map.on('click', function(e) {
            if (cableEdit.active) ftthCableEditClick(e);
        });
        map.on('mousemove', function(e) {
            if (cableEdit.active) ftthCableEditMove(e);
        });
        map.on('dblclick', function() {
            if (cableEdit.active) { L.DomEvent.stop(e); ftthCableEditFinish(); }
        });
        map.on('contextmenu', function(e) {
            if (cableEdit.active) { L.DomEvent.stop(e); ftthCableEditFinish(); }
        });
        document.addEventListener('keydown', function(e) {
            if (!cableEdit.active) return;
            if (e.key === 'Enter') { e.preventDefault(); ftthCableEditFinish(); }
            else if (e.key === 'Escape') { e.preventDefault(); ftthCableEditCancel(); }
            else if (e.key === 'r' || e.key === 'R') { ftthCableEditReset(); }
        });

        function ftthCableEditStart(m) {
            if (!m) return;
            var from = [Number(m.lat), Number(m.lon)];
            if (m.parent) {
                var pk = ftthSpotFromString(m.parent);
                var parent = ftthSpotMarkers[pk];
                if (parent) {
                    from = [Number(parent.lat), Number(parent.lon)];
                }
            }
            cableEdit.active = true;
            cableEdit.m = m;
            cableEdit.from = from;
            cableEdit.to = [Number(m.lat), Number(m.lon)];
            var attrs = (m.attributes && typeof m.attributes === 'object') ? m.attributes : {};
            if (Array.isArray(attrs.cable_path) && attrs.cable_path.length >= 2) {
                cableEdit.pts = attrs.cable_path.map(function(p) { return [Number(p[0]), Number(p[1])]; });
            } else {
                cableEdit.pts = [cableEdit.from.slice(), cableEdit.to.slice()];
            }
            cableEdit.color = (attrs.cable_color && /^#?[0-9a-fA-F]{6}$/.test(attrs.cable_color))
                ? (attrs.cable_color.charAt(0) === '#' ? attrs.cable_color : '#' + attrs.cable_color)
                : '#38bdf8';
            cableEdit.width = Number(attrs.cable_width) || 3;
            cableEdit.curve = !!attrs.cable_curve;
            var ceColor = document.getElementById('ftthCableColor');
            var ceWidth = document.getElementById('ftthCableWidth');
            var ceCurve = document.getElementById('ftthCableCurve');
            var ceWidthVal = document.getElementById('ftthCableWidthVal');
            if (ceColor) ceColor.value = cableEdit.color;
            if (ceWidth) ceWidth.value = cableEdit.width;
            if (ceCurve) ceCurve.checked = cableEdit.curve;
            if (ceWidthVal) ceWidthVal.textContent = cableEdit.width;
            cableEdit.layers = [];
            cableEdit.ghost = null;
            map.doubleClickZoom.disable();
            window.__ftthKeepPreZoom = true;
            ftthCloseAllCards();
            window.__ftthKeepPreZoom = false;
            ftthCableEditDraw();
            /* Zoom dekat langsung ke jalur kabel yang diedit */
            try {
                ftthCapturePreZoom();
                ftthFlyToPath(cableEdit.pts);
            } catch (e) {}
            ftthCableEditShowCard();
            ftthToast(ftthT('toast.cable_hint'), 'info');
        }

        function ftthCableEditClearLayers() {
            cableEdit.layers.forEach(function(l) { map.removeLayer(l); });
            cableEdit.layers = [];
            if (cableEdit.ghost) { map.removeLayer(cableEdit.ghost); cableEdit.ghost = null; }
        }

        function ftthCableEditStyle() {
            var ceColor = document.getElementById('ftthCableColor');
            var ceWidth = document.getElementById('ftthCableWidth');
            var ceCurve = document.getElementById('ftthCableCurve');
            var ceWidthVal = document.getElementById('ftthCableWidthVal');
            cableEdit.color = ceColor ? ceColor.value : cableEdit.color;
            cableEdit.width = ceWidth ? (Number(ceWidth.value) || 3) : cableEdit.width;
            cableEdit.curve = ceCurve ? ceCurve.checked : cableEdit.curve;
            if (ceWidthVal) ceWidthVal.textContent = cableEdit.width;
            ftthCableEditDraw();
        }

        function ftthCableEditDraw() {
            ftthCableEditClearLayers();
            var pts = cableEdit.pts;
            var color = cableEdit.color;
            var weight = cableEdit.width;
            var drawPts = (cableEdit.curve && pts.length >= 3) ? ftthCatmullRom(pts, 12) : pts;
            if (drawPts.length >= 2) {
                cableEdit.layers.push(L.polyline(drawPts, { color: color, weight: weight, dashArray: '8 6', opacity: 0.95 }).addTo(map));
            }
            pts.forEach(function(p, i) {
                var end = (i === 0 || i === pts.length - 1);
                cableEdit.layers.push(L.circleMarker(p, {
                    radius: end ? 5 : 4,
                    color: '#0b1524',
                    weight: 1.2,
                    fillColor: end ? '#38bdf8' : '#fbbf24',
                    fillOpacity: 1
                }).addTo(map));
            });
            ftthCableEditUpdateInfo();
        }

        function ftthCableEditClick(e) {
            if (!cableEdit.active) return;
            var p = [e.latlng.lat, e.latlng.lng];
            if (cableEdit.pts.length >= 2) {
                cableEdit.pts.splice(cableEdit.pts.length - 1, 0, p);
            } else {
                cableEdit.pts.push(p);
            }
            ftthCableEditDraw();
        }

        function ftthCableEditMove(e) {
            if (!cableEdit.active || cableEdit.pts.length === 0) return;
            if (cableEdit.ghost) map.removeLayer(cableEdit.ghost);
            var pts = cableEdit.pts.slice();
            pts.push([e.latlng.lat, e.latlng.lng]);
            cableEdit.ghost = L.polyline(pts, { color: '#38bdf8', weight: 2, dashArray: '3 6', opacity: 0.6 }).addTo(map);
        }

        function ftthCableEditFinish() {
            if (!cableEdit.active) return;
            if (cableEdit.pts.length < 2) { ftthToast(ftthT('toast.need_points'), 'warn'); return; }
            ftthCableEditStop();
            ftthRestorePreZoom();
            ftthCableEditSetStatus(ftthT('common.saving'));
            var m = cableEdit.m;
            mtApi('/noc/features/map/device/cable', 'POST', {
                id: m.id,
                cable_path: cableEdit.pts,
                cable_color: cableEdit.color,
                cable_width: cableEdit.width,
                cable_curve: cableEdit.curve ? 1 : 0
            }).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthToast(ftthT('toast.cable_saved'), 'ok');
                    loadMapMarkers();
                } else {
                    ftthToast((r.data && r.data.error) || ftthT('toast.cable_save_fail'), 'fail');
                }
                ftthCableEditHideCard();
            }).catch(function() {
                ftthToast(ftthT('toast.cable_save_fail'), 'fail');
                ftthCableEditHideCard();
            });
        }

        function ftthCableEditCancel() {
            if (!cableEdit.active) return;
            ftthCableEditStop();
            ftthRestorePreZoom();
            ftthCableEditHideCard();
            ftthToast(ftthT('toast.cable_cancelled'), 'info');
            loadMapMarkers();
        }

        function ftthCableEditReset() {
            if (!cableEdit.active) return;
            cableEdit.pts = [cableEdit.from.slice(), cableEdit.to.slice()];
            ftthCableEditDraw();
        }

        function ftthCableEditStop() {
            cableEdit.active = false;
            ftthCableEditClearLayers();
            if (!mapLocked) map.doubleClickZoom.enable();
        }

        function ftthCableEditShowCard() {
            var card = document.getElementById('ftthCableEditCard');
            if (card) card.style.display = 'block';
            ftthCableEditUpdateInfo();
        }

        function ftthCableEditHideCard() {
            var card = document.getElementById('ftthCableEditCard');
            if (card) card.style.display = 'none';
        }

        function ftthCableEditSetStatus(msg) {
            var el = document.getElementById('ftthCableEditStatus');
            if (el) el.textContent = msg;
        }

        function ftthCableEditUpdateInfo() {
            var body = document.getElementById('ftthCableEditBody');
            if (!body) return;
            var n = cableEdit.pts.length;
            var dist = 0;
            for (var i = 1; i < cableEdit.pts.length; i++) {
                dist += map.distance(cableEdit.pts[i - 1], cableEdit.pts[i]);
            }
            var dTxt = dist >= 1000 ? (dist / 1000).toFixed(2) + ' km' : Math.round(dist) + ' m';
            body.innerHTML =
                '<div class="fm-row"><span>Titik</span><b>' + n + '</b></div>' +
                '<div class="fm-row"><span>Panjang jalur</span><b>' + dTxt + '</b></div>';
        }

        /* ── Card properti kabel (slide-out) + mode reposisi kabel ── */
        var cableProp = { m: null, open: false };
        var cableRepos = { active: false, m: null, pts: [], line: null, verts: [], live: null, origPts: null };

        function ftthCableNormColor(v) {
            v = String(v || '');
            return /^#[0-9a-fA-F]{6}$/.test(v) ? v : (/^[0-9a-fA-F]{6}$/.test(v) ? '#' + v : '#38bdf8');
        }

        function ftthHexToRgba(hex, alpha) {
            var h = String(hex || '').replace('#', '');
            if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
            if (!/^[0-9a-fA-F]{6}$/.test(h)) return 'rgba(125,211,252,' + alpha + ')';
            var n = parseInt(h, 16);
            return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + alpha + ')';
        }

        function ftthCableAnimClass(anim, online) {
            if (anim === 'dash') return 'ftth-cable-anim-dash';
            if (anim === 'glow-fast') return 'ftth-cable-anim-glow-fast';
            if (anim === 'glow-slow') return 'ftth-cable-anim-glow-slow';
            if (anim === 'none') return '';
            return online ? 'ftth-cable-flow' : 'ftth-cable-stop';
        }

        /* Simpan tampilan peta sebelum zoom-in edit kabel/reposisi,
           agar bisa dikembalikan saat edit selesai / card ditutup */
        var ftthPreZoom = null;
        function ftthCapturePreZoom() {
            if (ftthPreZoom) return;
            try { ftthPreZoom = { c: map.getCenter(), z: map.getZoom() }; } catch (e) {}
        }
        function ftthRestorePreZoom() {
            if (!ftthPreZoom) return;
            ftthFlyMap(ftthPreZoom.c, ftthPreZoom.z);
            ftthPreZoom = null;
        }
        /* Terbang beranimasi (zoom in/out mulus, tanpa patah-patah):
           - map.stop(): matikan inersia drag sebelum mulai
           - class .ftth-flying: pause animasi CSS dekoratif selama terbang
           - durasi adaptif menurut jarak */
        function ftthFlyMap(center, zoom) {
            try {
                try { map.stop(); } catch (e) {}
                var dur = 1.2;
                try {
                    var dist = map.getCenter().distanceTo(center);
                    dur = Math.min(2.2, Math.max(1.0, dist / 300000));
                } catch (e) {}
                var cont = map.getContainer();
                cont.classList.add('ftth-flying');
                var unfly = function() { cont.classList.remove('ftth-flying'); };
                map.once('moveend', unfly);
                if (ftthFlyMap._t) clearTimeout(ftthFlyMap._t);
                ftthFlyMap._t = setTimeout(unfly, Math.ceil(dur * 1000) + 600); /* pengaman bila moveend tak terpicu */
                map.flyTo(center, zoom, { duration: dur });
            } catch (e) {}
        }
        /* Zoom-in beranimasi (transisi mulus dari jauh ke dekat) menuju jalur kabel */
        function ftthFlyToPath(pts) {
            try {
                var b = L.polyline(pts).getBounds();
                ftthFlyMap(b.getCenter(), 20);
            } catch (e) {}
        }

        window.ftthCablePropsOpen = function(m) {
            if (!m || m.id == null) return;
            cableProp.m = m;
            var a = (m.attributes && typeof m.attributes === 'object') ? m.attributes : {};
            var w = document.getElementById('fcpWidth');
            var c = document.getElementById('fcpColor');
            var an = document.getElementById('fcpAnim');
            var wv = document.getElementById('fcpWidthVal');
            var info = document.getElementById('fcpInfo');
            var title = document.getElementById('fcpTitle');
            if (w) w.value = Number(a.cable_width) || 3;
            if (c) c.value = ftthCableNormColor(a.cable_color);
            var mc = document.getElementById('fcpMeteor');
            if (mc) mc.value = ftthCableNormColor(a.cable_meteor_color);
            if (an) an.value = a.cable_anim || '';
            if (wv) wv.textContent = Number(a.cable_width) || 3;
            if (title) title.textContent = 'Edit Kabel — ' + (m.label || '');
            if (info) info.textContent = (Array.isArray(a.cable_path) && a.cable_path.length >= 2)
                ? 'Jalur kustom: ' + a.cable_path.length + ' titik'
                : 'Jalur kustom: belum diatur (garis lurus)';
            /* Zoom dekat ke kabel ini (card tetap tampil); simpan dulu tampilan semula */
            try {
                ftthCapturePreZoom();
                var zTo = [Number(m.lat), Number(m.lon)];
                var zFrom = null;
                if (m.parent) {
                    var zPm = ftthSpotMarkers[ftthSpotFromString(m.parent)];
                    if (zPm) zFrom = [Number(zPm.lat), Number(zPm.lon)];
                }
                var zPts = (Array.isArray(a.cable_path) && a.cable_path.length >= 2)
                    ? a.cable_path.map(function(p) { return [Number(p[0]), Number(p[1])]; })
                    : (zFrom ? [zFrom.slice(), zTo.slice()] : null);
                if (zPts) {
                    ftthFlyToPath(zPts);
                }
            } catch (e) {}
            var card = document.getElementById('ftthCablePropsCard');
            var dc = document.getElementById('ftthDetailCard');
            if (!card) return;
            var r = dc.getBoundingClientRect();
            var cw = card.offsetWidth || 252;
            var left = r.right + 10;
            if (left + cw > window.innerWidth - 8) left = Math.max(8, r.left - cw - 10);
            var top = Math.max(8, Math.min(r.top, window.innerHeight - 280));
            card.style.left = left + 'px';
            card.style.top = top + 'px';
            card.classList.add('behind');
            card.style.display = 'block';
            requestAnimationFrame(function() {
                requestAnimationFrame(function() { card.classList.remove('behind'); });
            });
            cableProp.open = true;
        };

        /* Kembalikan style kabel ke kondisi tersimpan tanpa reload peta */
        window.ftthCablePropsRevert = function() {
            if (!cableProp.m || !cableLayer) return;
            var m = cableProp.m;
            var a = (m.attributes && typeof m.attributes === 'object') ? m.attributes : {};
            var online = (m.status === 'online' || m.onu_status === 'online');
            var color = a.cable_color || a.warna_core || ((String(m.type || '').toUpperCase() === 'ONU') ? '#3b82f6' : ftthDeviceColor(m.type));
            var width = Number(a.cable_width) || (online ? 2.5 : 2);
            cableLayer.getLayers().forEach(function(pl) {
                if (pl._cableMarkerId !== m.id) return;
                pl.setStyle({ color: color, weight: width });
                var gel = pl.getElement ? pl.getElement() : null;
                if (gel) gel.style.setProperty('--glowc', color);
                pl.options._cableAnim = a.cable_anim || '';
                pl.options._cableMeteor = a.cable_meteor_color || '';
                document.querySelectorAll('g[data-ftth-meteor="' + pl._cableMarkerId + '"]').forEach(function(n) { n.remove(); });
                if ((a.cable_anim === 'glow-fast' || a.cable_anim === 'glow-slow') && online) {
                    ftthAddMeteors(pl, a.cable_anim, a.cable_meteor_color);
                }
            });
        };

        window.ftthCablePropsClose = function() {
            ftthCablePropsRevert();
            var card = document.getElementById('ftthCablePropsCard');
            if (card) card.style.display = 'none';
            cableProp.open = false;
            /* Saat transisi ke mode reposisi (__ftthKeepPreZoom), zoom-in dipertahankan —
               jangan restore di sini agar tidak menangkap posisi tengah animasi terbang */
            if (!window.__ftthKeepPreZoom) ftthRestorePreZoom();
        };

        window.ftthCablePropsCancel = function() {
            ftthCablePropsClose();
        };

        window.ftthCablePropsOpenForDetail = function() {
            if (ftthDetailData) ftthCablePropsOpen(ftthDetailData);
        };

        window.ftthCableEditStartForDetail = function() {
            if (ftthDetailData) ftthCableEditStart(ftthDetailData);
        };

        function ftthCablePropsRead() {
            var w = document.getElementById('fcpWidth');
            var c = document.getElementById('fcpColor');
            var an = document.getElementById('fcpAnim');
            var mc = document.getElementById('fcpMeteor');
            return {
                width: w ? (Number(w.value) || 3) : 3,
                color: c ? c.value : '#38bdf8',
                anim: an ? an.value : '',
                meteor: mc ? mc.value : '#38bdf8'
            };
        }

        window.ftthCablePropsPreview = function() {
            var st = ftthCablePropsRead();
            var wv = document.getElementById('fcpWidthVal');
            if (wv) wv.textContent = st.width;
            if (!cableProp.m || !cableLayer) return;
            cableLayer.getLayers().forEach(function(pl) {
                if (pl._cableMarkerId !== cableProp.m.id) return;
                pl.setStyle({ color: st.color, weight: st.width });
                pl.options._cableAnim = st.anim || '';
                pl.options._cableMeteor = st.meteor;
                var el = pl.getElement();
                if (el) {
                    el.style.setProperty('--glowc', st.color);
                    el.classList.remove('ftth-cable-flow', 'ftth-cable-stop', 'ftth-cable-anim-dash', 'ftth-cable-anim-glow-fast', 'ftth-cable-anim-glow-slow');
                    var cls = ftthCableAnimClass(st.anim, pl.options._cableOnline);
                    if (cls) el.classList.add(cls);
                }
                document.querySelectorAll('g[data-ftth-meteor="' + pl._cableMarkerId + '"]').forEach(function(n) { n.remove(); });
                if ((st.anim === 'glow-fast' || st.anim === 'glow-slow') && pl.options._cableOnline) {
                    ftthAddMeteors(pl, st.anim, st.meteor);
                }
            });
        };

        window.ftthCablePropsSave = function() {
            if (!cableProp.m) return;
            var st = ftthCablePropsRead();
            var a = (cableProp.m.attributes && typeof cableProp.m.attributes === 'object') ? cableProp.m.attributes : {};
            var payload = {
                id: cableProp.m.id,
                cable_color: st.color,
                cable_width: st.width,
                cable_curve: a.cable_curve ? 1 : 0,
                cable_anim: st.anim || null,
                cable_meteor_color: st.meteor
            };
            mtApi('/noc/features/map/device/cable', 'POST', payload).then(function(r) {
                if (r.data && r.data.ok) {
                    cableProp.m.attributes = Object.assign({}, a, { cable_color: st.color, cable_width: st.width, cable_meteor_color: st.meteor });
                    if (st.anim) cableProp.m.attributes.cable_anim = st.anim; else delete cableProp.m.attributes.cable_anim;
                    ftthToast(ftthT('toast.cable_saved'), 'ok');
                    ftthRestorePreZoom();
                    loadMapMarkers();
                } else {
                    ftthToast((r.data && r.data.error) || ftthT('toast.cable_save_fail'), 'fail');
                }
            }).catch(function() { ftthToast(ftthT('toast.cable_save_fail'), 'fail'); });
        };

        window.ftthCablePropsDelete = function() {
            if (!cableProp.m) return;
            if (!confirm('Hapus jalur kabel "' + (cableProp.m.label || '') + '"?')) return;
            mtApi('/noc/features/map/device/cable', 'POST', { id: cableProp.m.id, clear: true }).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthToast('Kabel dihapus', 'ok');
                    ftthCablePropsClose();
                    loadMapMarkers();
                } else {
                    ftthToast((r.data && r.data.error) || 'Gagal hapus kabel', 'fail');
                }
            }).catch(function() { ftthToast('Gagal hapus kabel', 'fail'); });
        };

        function ftthCableSegDist(p, a, b) {
            var dx = b[0] - a[0], dy = b[1] - a[1];
            var l2 = dx * dx + dy * dy;
            var t = l2 ? ((p[0] - a[0]) * dx + (p[1] - a[1]) * dy) / l2 : 0;
            t = Math.max(0, Math.min(1, t));
            var px = a[0] + t * dx, py = a[1] + t * dy;
            return ((p[0] - px) * (p[0] - px)) + ((p[1] - py) * (p[1] - py));
        }

        function ftthCableNearestSegmentIdx(p) {
            var best = 1, bestD = Infinity;
            for (var i = 1; i < cableRepos.pts.length; i++) {
                var d = ftthCableSegDist(p, cableRepos.pts[i - 1], cableRepos.pts[i]);
                if (d < bestD) { bestD = d; best = i; }
            }
            return best;
        }

        function ftthCableReposClear() {
            if (cableRepos.line) { map.removeLayer(cableRepos.line); cableRepos.line = null; }
            cableRepos.verts.forEach(function(v) { map.removeLayer(v); });
            cableRepos.verts = [];
        }

        /* Bentuk akhir kabel inti: ikuti kurva bila attr cable_curve aktif */
        function ftthCableReposLivePts(pts) {
            var m = cableRepos.m;
            var a = (m && m.attributes && typeof m.attributes === 'object') ? m.attributes : {};
            return (a.cable_curve && pts.length >= 3) ? ftthCatmullRom(pts, 12) : pts;
        }

        /* Sinkronkan kabel inti di peta dengan titik yang sedang diseret */
        function ftthCableReposSyncLive() {
            if (!cableRepos.live || typeof cableRepos.live.setLatLngs !== 'function') return;

            try {
                cableRepos.live.setLatLngs(ftthCableReposLivePts(cableRepos.pts));
            } catch (e) {}
        }

        function ftthCableReposDraw() {
            if (cableRepos.line) map.removeLayer(cableRepos.line);
            cableRepos.verts.forEach(function(v) { map.removeLayer(v); });
            cableRepos.verts = [];
            var st = ftthCablePropsRead();
            cableRepos.line = L.polyline(cableRepos.pts, { color: st.color, weight: Math.max(3, st.width), opacity: 0.95 }).addTo(map);
            cableRepos.line.on('click', function(e) {
                L.DomEvent.stop(e);
                if (!cableRepos.active) return;
                var p = [e.latlng.lat, e.latlng.lng];
                cableRepos.pts.splice(ftthCableNearestSegmentIdx(p), 0, p);
                ftthCableReposDraw();
            });
            var icon = L.divIcon({ className: 'ftth-cable-vx', html: '<div class="ftth-cable-vx-i"></div>', iconSize: [12, 12], iconAnchor: [6, 6] });
            cableRepos.pts.forEach(function(p, i) {
                var mk = L.marker(p, { icon: icon, draggable: true, zIndexOffset: 1000 }).addTo(map);
                mk.on('drag', function(ev) {
                    cableRepos.pts[i] = [ev.latlng.lat, ev.latlng.lng];
                    if (cableRepos.line) cableRepos.line.setLatLngs(cableRepos.pts);
                    /* Kabel inti ikut terseret real-time */
                    ftthCableReposSyncLive();
                });
                mk.on('contextmenu', function(ev) {
                    L.DomEvent.stop(ev);
                    if (!cableRepos.active) return;
                    if (i === 0 || i === cableRepos.pts.length - 1 || cableRepos.pts.length <= 2) return;
                    cableRepos.pts.splice(i, 1);
                    ftthCableReposDraw();
                });
                cableRepos.verts.push(mk);
            });
            ftthCableReposSyncLive();
        }

        function ftthCableRepositionStop(keepLiveShape) {
            cableRepos.active = false;
            /* Batal: kembalikan kabel inti ke bentuk awal. Setelah simpan
               (keepLiveShape), bentuk baru dipertahankan sampai reload data */
            if (!keepLiveShape && cableRepos.live && typeof cableRepos.live.setLatLngs === 'function' && cableRepos.origPts) {
                try { cableRepos.live.setLatLngs(ftthCableReposLivePts(cableRepos.origPts)); } catch (e) {}
            }
            cableRepos.live = null;
            cableRepos.origPts = null;
            ftthCableReposClear();
            var bar = document.getElementById('ftthRepositionBar');
            if (bar) bar.style.display = 'none';
        }

        window.ftthCableRepositionStart = function() {
            if (!cableProp.m) return;
            var m = cableProp.m;
            var a = (m.attributes && typeof m.attributes === 'object') ? m.attributes : {};
            var to = [Number(m.lat), Number(m.lon)];
            var from = null;
            if (m.parent) {
                var pm = ftthSpotMarkers[ftthSpotFromString(m.parent)];
                if (pm) from = [Number(pm.lat), Number(pm.lon)];
            }
            /* Tutup card lain tanpa me-restore zoom card properti —
               ftthPreZoom dari props open tetap dipakai (tampilan semula) */
            window.__ftthKeepPreZoom = true;
            ftthCloseAllCards();
            window.__ftthKeepPreZoom = false;
            cableProp.m = m;
            cableRepos.m = m;
            cableRepos.pts = (Array.isArray(a.cable_path) && a.cable_path.length >= 2)
                ? a.cable_path.map(function(p) { return [Number(p[0]), Number(p[1])]; })
                : (from ? [from.slice(), to.slice()] : [[to[0] - 0.0005, to[1] - 0.0005], to.slice()]);
            cableRepos.origPts = cableRepos.pts.map(function(p) { return p.slice(); });
            /* Referensi kabel inti di layer peta agar ikut terseret real-time */
            cableRepos.live = null;
            if (typeof cableLayer !== 'undefined' && cableLayer) {
                cableLayer.eachLayer(function(l) {
                    if (!cableRepos.live && l._cableMarkerId === m.id) cableRepos.live = l;
                });
            }
            cableRepos.active = true;
            ftthCableReposDraw();
            try {
                ftthCapturePreZoom();
                ftthFlyToPath(cableRepos.pts);
            } catch (e) {}
            var bar = document.getElementById('ftthRepositionBar');
            if (bar) bar.style.display = 'flex';
            var card = document.getElementById('ftthCablePropsCard');
            if (card) {
                var ch2 = card.offsetHeight || 320;
                card.style.left = '8px';
                card.style.top = Math.max(8, Math.round((window.innerHeight - ch2) / 2)) + 'px';
                card.classList.remove('behind');
                card.style.display = 'block';
            }
            cableProp.open = true;
        };

        window.ftthCableRepositionFinish = function() {
            if (!cableRepos.active || !cableRepos.m) return;
            var m = cableRepos.m;
            var a = (m.attributes && typeof m.attributes === 'object') ? m.attributes : {};
            var st = ftthCablePropsRead();
            var payload = {
                id: m.id,
                cable_path: cableRepos.pts,
                cable_color: st.color,
                cable_width: st.width,
                cable_curve: a.cable_curve ? 1 : 0
            };
            if (a.cable_anim) payload.cable_anim = a.cable_anim;
            ftthCableRepositionStop(true);
            ftthRestorePreZoom();
            mtApi('/noc/features/map/device/cable', 'POST', payload).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthToast(ftthT('toast.cable_saved'), 'ok');
                    loadMapMarkers();
                } else {
                    ftthToast((r.data && r.data.error) || ftthT('toast.cable_save_fail'), 'fail');
                }
            }).catch(function() { ftthToast(ftthT('toast.cable_save_fail'), 'fail'); });
        };

        window.ftthCableRepositionCancel = function() {
            ftthCableRepositionStop();
            ftthRestorePreZoom();
            loadMapMarkers();
        };

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && cableRepos.active) { e.preventDefault(); ftthCableRepositionCancel(); }
        });

        /* ── Kalkulator Redaman: hitung redaman fiber ── */
        var CALC = {
            plc: { 2: 3.25, 4: 7.0, 8: 10.0, 16: 13.5, 32: 17.0, 64: 20.0 },
            dbPerKm: 0.30,
            spliceDb: 0.03,
            connectorDb: 0.30,
            ratioLoss: {
                '1:99':20.20, '2:98':17.19, '3:97':15.43, '4:96':14.18, '5:95':13.21,
                '6:94':12.22, '8:92':10.87, '9:91':10.38, '10:90':10.20, '12:88':9.21,
                '15:85':8.44, '20:80':7.19, '25:75':6.22, '30:70':5.43,
                '35:65':4.70, '40:60':4.18, '45:55':3.47, '50:50':3.21
            },
            ratioPass: {
                '1:99':0.24, '2:98':0.29, '3:97':0.33, '4:96':0.38, '5:95':0.42,
                '6:94':0.47, '8:92':0.58, '9:91':0.63, '10:90':0.66, '12:88':0.79,
                '15:85':0.91, '20:80':1.17, '25:75':1.45, '30:70':1.75,
                '35:65':2.05, '40:60':2.42, '45:55':2.92, '50:50':3.21
            }
        };

        window.ftthCalcSetMode = function(mode) {
            var adv = document.getElementById('fcAdvFields');
            var btnSimple = document.getElementById('fcModeSimple');
            var btnAdv = document.getElementById('fcModeAdv');
            var card = document.getElementById('ftthCalcCard');
            if (mode === 'advanced') {
                adv.style.display = '';
                btnSimple.classList.remove('active');
                btnAdv.classList.add('active');
                card.classList.add('ftth-calc-adv-mode');
                var pin = document.getElementById('fcInputPower');
                if (pin) pin.value = '';
            } else {
                adv.style.display = 'none';
                btnSimple.classList.add('active');
                btnAdv.classList.remove('active');
                card.classList.remove('ftth-calc-adv-mode');
                var pin2 = document.getElementById('fcInputPower');
                if (pin2) pin2.value = '';
                ftthCalcResetAdvFields();
            }
            ftthCalcUpdate();
            ftthSyncCalcRef();
        };

        function ftthCalcResetAdvFields() {
            var d = document.getElementById('fcDistance');
            var s = document.getElementById('fcSplices');
            var c = document.getElementById('fcConnectors');
            if (d) d.value = '';
            if (s) s.value = '';
            if (c) { c.value = ''; ftthCalcSyncPlaceholder(c); }
        }

        function ftthSyncCalcRef() {
            var card = document.getElementById('ftthCalcCard');
            var ref = document.getElementById('ftthCalcRef');
            if (!card || !ref) return;
            if (window.innerWidth <= 767) { ref.style.height = ''; return; }
            ref.style.height = card.offsetHeight + 'px';
        }
        window.addEventListener('resize', ftthSyncCalcRef);

        window.ftthCalcToggleRef = function() {
            document.getElementById('ftthCalcWrap').classList.toggle('ref-open');
            ftthSyncCalcRef();
        };

        function ftthCalcGetOntStatus(dbm) {
            if (dbm > -8) return { cls: 'status-strong', icon: 'fa-solid fa-circle', text: 'Sinyal Terlalu Kuat (daya berlebih)', color: '#facc15' };
            if (dbm >= -24) return { cls: 'status-optimal', icon: 'fa-solid fa-circle', text: 'Unggul / Optimal', color: '#4ade80' };
            if (dbm >= -27) return { cls: 'status-warn', icon: 'fa-solid fa-circle', text: 'Peringatan / Batas', color: '#fbbf24' };
            return { cls: 'status-bad', icon: 'fa-solid fa-circle', text: 'Risiko Atenuasi Buruk / Tinggi (LOS)', color: '#f87171' };
        }

        /* ── Tutup semua card sekaligus agar antar-card tidak menumpuk ── */
        function ftthCloseAllCards() {
            ftthCloseCalc();
            ftthCloseVis();
            ftthCloseMikrotik();
            ftthCloseOlt();
            ftthCloseGenieacs();
            ftthCloseNotifWa();
            ftthCloseNotifTg();
            ftthCloseQueue();
            ftthCloseHotspot();
            ftthCloseBackup();
            ftthCloseAddDevice();
            ftthCloseDevices();
            ftthCloseOnuTable();
            ftthOnuStopTraffic();
            ftthCloseDetail();
            ftthCloseUsers();
            if (typeof ftthCablePropsClose === 'function') ftthCablePropsClose();
            if (typeof cableRepos !== 'undefined' && cableRepos.active) {
                cableRepos.active = false;
                ftthCableReposClear();
                var rbar = document.getElementById('ftthRepositionBar');
                if (rbar) rbar.style.display = 'none';
            }
            ftthForceCloseMeasure();
        }

        window.ftthOpenCalc = function() {
            ftthCloseAllCards();
            var bd = document.getElementById('ftthCalcBackdrop');
            var wrap = document.getElementById('ftthCalcWrap');
            var card = document.getElementById('ftthCalcCard');
            var btn = document.querySelector('.ftth-ac-calculator');
            bd.hidden = false;
            bd.style.display = '';
            var pad = 8;
            var ww = wrap.offsetWidth || 380;
            var wh = wrap.offsetHeight || 480;
            var left, top;
            if (btn) {
                var r = btn.getBoundingClientRect();
                left = r.left;
                top = r.bottom + pad;
                if (left + ww > window.innerWidth - pad) left = Math.max(pad, window.innerWidth - ww - pad);
                if (top + wh > window.innerHeight - pad) top = Math.max(pad, window.innerHeight - wh - pad);
            } else {
                left = window.innerWidth / 2 - ww / 2;
                top = window.innerHeight / 2 - wh / 2;
            }
            wrap.style.position = 'fixed';
            wrap.style.left = left + 'px';
            wrap.style.top = top + 'px';
            wrap.style.margin = '0';
            wrap.style.transform = 'none';
            card.style.position = '';
            card.style.left = '';
            card.style.right = '';
            card.style.transform = '';
            card.style.zIndex = '';
            ftthCalcSetMode('simple');
            wrap.classList.remove('ref-open');
            ftthCalcUpdate();
        };

        window.ftthCloseCalc = function() {
            var bd = document.getElementById('ftthCalcBackdrop');
            var wrap = document.getElementById('ftthCalcWrap');
            var card = document.getElementById('ftthCalcCard');
            bd.hidden = true;
            bd.style.display = '';
            bd.style.alignItems = '';
            bd.style.justifyContent = '';
            wrap.style.position = '';
            wrap.style.left = '';
            wrap.style.top = '';
            wrap.style.margin = '';
            wrap.style.transform = '';
            card.style.position = '';
            card.style.left = '';
            card.style.right = '';
            card.style.transform = '';
            card.style.zIndex = '';
            var pin = document.getElementById('fcInputPower');
            if (pin) pin.value = '';
            ftthCalcResetAdvFields();
            ftthCalcUpdate();
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
            var plc = parseInt(document.getElementById('fcPlc').value, 10) || 0;

            var isAdv = document.getElementById('fcAdvFields').style.display !== 'none';
            var dist = 0, km = 0, splices = 0, connectors = 0;
            if (isAdv) {
                var unit = document.getElementById('fcUnit').value;
                dist = ftthCalcNum('fcDistance');
                km = unit === 'm' ? dist / 1000 : dist;
                splices = Math.round(ftthCalcNum('fcSplices'));
                connectors = parseInt(document.getElementById('fcConnectors').value, 10) || 0;
            }

            var ratioLoss = ratio ? (CALC.ratioLoss[ratio] || 0) : 0;
            var ratioPass = ratio ? (CALC.ratioPass[ratio] || 0) : 0;
            var plcLoss = plc ? (CALC.plc[plc] || 0) : 0;
            var cableLoss = km * CALC.dbPerKm;
            var spliceLoss = splices * CALC.spliceDb;
            var connLoss = connectors * CALC.connectorDb;
            var totalSplitter = ratioLoss + plcLoss;
            var totalLoss = totalSplitter + cableLoss + spliceLoss + connLoss;

            var drop = pin - totalLoss;
            var pass = pin - ratioPass - cableLoss - spliceLoss - connLoss;

            document.getElementById('fcDetailSplit').textContent = totalSplitter.toFixed(2) + ' dB';
            document.getElementById('fcDetailCable').textContent = cableLoss.toFixed(2) + ' dB';
            document.getElementById('fcDetailSplice').textContent = spliceLoss.toFixed(2) + ' dB';
            document.getElementById('fcDetailConn').textContent = connLoss.toFixed(2) + ' dB';
            document.getElementById('fcDetailTotal').textContent = totalLoss.toFixed(2) + ' dB';
            document.getElementById('fcOntPower').textContent = drop.toFixed(2) + ' dBm';
            document.getElementById('fcPassPower').textContent = pass.toFixed(2) + ' dBm';

            var status = ftthCalcGetOntStatus(drop);
            var box = document.getElementById('fcOntPowerBox');
            box.className = 'ftth-calc-ont-power ' + status.cls;
            var statusBox = document.getElementById('fcOntStatusBox');
            statusBox.className = 'ftth-calc-status-inner ' + status.cls;
            var statusEl = document.getElementById('fcOntStatus');
            statusEl.innerHTML = '<i class="' + status.icon + '"></i> ' + status.text;

            var totalRow = document.getElementById('fcDetailTotalRow');
            totalRow.className = 'ftth-calc-detail-row ftth-calc-detail-total ' + status.cls;

            var odpRow = document.getElementById('fcOdpRow');
            if (ratio) {
                odpRow.style.display = '';
            } else {
                odpRow.style.display = 'none';
            }
        }

        document.getElementById('fcRatio').addEventListener('change', ftthCalcUpdate);
        document.getElementById('fcPlc').addEventListener('change', ftthCalcUpdate);
        document.getElementById('fcUnit').addEventListener('change', ftthCalcUpdate);
        document.getElementById('fcConnectors').addEventListener('change', function() {
            ftthCalcUpdate();
            ftthCalcSyncPlaceholder(this);
        });
        function ftthCalcSyncPlaceholder(sel) {
            if (!sel) return;
            if (sel.value === '') sel.classList.add('ftth-placeholder');
            else sel.classList.remove('ftth-placeholder');
        }
        ftthCalcSyncPlaceholder(document.getElementById('fcConnectors'));
        ['fcInputPower', 'fcDistance', 'fcSplices'].forEach(function(id) {
            document.getElementById(id).addEventListener('input', ftthCalcUpdate);
        });

        /* ── Visibility: filter layer & sembunyikan tombol ── */
        var VIS = {
            router: true,
            odc: true,
            odp: true,
            otb: true,
            closure: true,
            onuOnline: true,
            onuOffline: true,
            onuText: 'sembunyi',
            cable: true,
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
            var notifHidden = VIS.hideNotif;
            if (notifBtn) notifBtn.classList.toggle('ftth-vis-hidden', notifHidden);
            if (notifWrap) notifWrap.classList.toggle('ftth-vis-hidden', notifHidden);
            ftthApplyPermissions();
            if (markersCache) renderMapMarkers();
        }

        var FTTH_PERM_MAP = {
            'edit-map': 'edit_map',
            'sync-mikrotik': 'sync_mikrotik',
            'sync-olt': 'sync_olt',
            'sync-genieacs': 'sync_genieacs',
            'ganti-wifi': 'ganti_wifi',
            'import-export': 'import_export'
        };
        function ftthCan(perm) {
            return !!window.FTTH_USER.permissions[perm];
        }
        function ftthApplyPermissions() {
            document.querySelectorAll('[data-feature]').forEach(function(el) {
                var f = el.getAttribute('data-feature');
                if (f === 'users') {
                    var ur = window.FTTH_USER.role;
                    if (ur !== 'noc' && ur !== 'superadmin') el.classList.add('ftth-perm-denied');
                    return;
                }
                var perm = FTTH_PERM_MAP[f];
                if (!perm) return;
                if (ftthCan(perm)) el.classList.remove('ftth-perm-denied');
                else el.classList.add('ftth-perm-denied');
            });
        }
        ftthApplyPermissions();

        window.ftthOpenVis = function() {
            ftthCloseAllCards();
            document.getElementById('ftthVisBackdrop').hidden = false;
            positionCardBelow(document.getElementById('ftthVisCard'), document.querySelector('.ftth-ac-visibility'));
            ftthVisApplyPerms();
        };

        function ftthVisApplyPerms() {
            var body = document.getElementById('ftthVisHideBody');
            if (!body) return;
            body.querySelectorAll('.ftth-vis-check[data-feature]').forEach(function(lbl) {
                var f = lbl.getAttribute('data-feature');
                var show = true;
                if (f === 'users') {
                    var r = window.FTTH_USER.role;
                    show = (r === 'noc' || r === 'superadmin');
                } else {
                    var perm = FTTH_PERM_MAP[f];
                    if (perm) show = ftthCan(perm);
                }
                lbl.style.display = show ? '' : 'none';
            });
        }

        window.ftthCloseVis = function() {
            document.getElementById('ftthVisBackdrop').hidden = true;
        };

        /* ── Card Pengaturan User ── */
        var ftthUsersData = [];
        var ftthUsersEditId = null;

        window.ftthOpenUsers = function() {
            ftthCloseAllCards();
            document.getElementById('ftthUserFormBackdrop').hidden = true;
            document.getElementById('ftthUsersBackdrop').hidden = false;
            positionCardBelow(document.getElementById('ftthUsersCard'), document.querySelector('.ftth-ac-users'));
            renderUsers();
            loadUsers();
        };
        window.ftthCloseUsers = function() {
            document.getElementById('ftthUsersBackdrop').hidden = true;
            document.getElementById('ftthUserFormBackdrop').hidden = true;
        };
        function loadUsers() {
            var wrap = document.getElementById('ftthUsersList');
            if (wrap) wrap.innerHTML = ftthLoaderHtml(ftthT('loader.users'));
            mtApi('/noc/features/map/users', 'GET').then(function(r) {
                ftthUsersData = r.data.users || [];
                renderUsers();
            }).catch(function() {
                ftthToast(ftthT('toast.user_load_fail'), 'error');
            });
        }
        function renderUsers() {
            var wrap = document.getElementById('ftthUsersList');
            if (!ftthUsersData.length) {
                wrap.innerHTML = '<div class="ftth-user-empty">' + ftthT('users.empty') + '</div>';
                return;
            }
            wrap.innerHTML = ftthUsersData.map(function(u) {
                var roleLabel = u.role === 'noc' ? 'NOC' : (u.role === 'admin' ? 'ADMIN' : (u.role === 'sales' ? 'SALES' : 'TEKNISI'));
                return '<div class="ftth-user-row">'
                    + '<div class="ftth-user-main">'
                    + '<div class="ftth-user-name">' + escapeHtml(u.username) + '</div>'
                    + '<span class="ftth-user-role">' + roleLabel + '</span>'
                    + '</div>'
                    + '<div class="ftth-user-actions">'
                    + '<button type="button" class="ftth-user-edit" title="' + ftthT('detail.edit_btn') + '" onclick="ftthUserEdit(' + u.id + ')"><i class="fa-solid fa-pencil"></i></button>'
                    + '<button type="button" class="ftth-user-del" title="' + ftthT('detail.hapus_btn') + '" onclick="ftthUserDelete(' + u.id + ')"><i class="fa-solid fa-trash"></i></button>'
                    + '</div>'
                    + '</div>';
            }).join('');
        }
        function ftthUserFillPerms(perms, role) {
            perms = perms || {};
            document.getElementById('ftthPermEditMap').checked = !!perms.edit_map;
            document.getElementById('ftthPermMikrotik').checked = !!perms.sync_mikrotik;
            document.getElementById('ftthPermOlt').checked = !!perms.sync_olt;
            document.getElementById('ftthPermGenieacs').checked = !!perms.sync_genieacs;
            document.getElementById('ftthPermWifi').checked = !!perms.ganti_wifi;
            document.getElementById('ftthPermExcel').checked = !!perms.import_export;
            document.getElementById('ftthPermPanel').checked = (role === 'admin' || role === 'teknisi') ? true : !!perms.panel_ftth;
        }
        window.ftthUserTogglePass = function() {
            var inp = document.getElementById('ftthUserPass');
            var icon = document.getElementById('ftthUserPassToggleIcon');
            if (!inp || !icon) return;
            if (inp.type === 'password') {
                inp.type = 'text';
                icon.className = 'fa-solid fa-eye-slash';
            } else {
                inp.type = 'password';
                icon.className = 'fa-solid fa-eye';
            }
        };
        window.ftthUserShowForm = function(user) {
            var listCard = document.getElementById('ftthUsersCard');
            var formCard = document.getElementById('ftthUserFormCard');
            document.getElementById('ftthUsersBackdrop').hidden = true;
            document.getElementById('ftthUserFormBackdrop').hidden = false;
            formCard.style.left = listCard.style.left;
            formCard.style.top = listCard.style.top;
            formCard.style.transform = 'none';
            formCard._dragged = listCard._dragged;
            positionCardBelow(formCard, document.querySelector('.ftth-ac-users'));
            if (user) {
                ftthUsersEditId = user.id;
                document.getElementById('ftthUserFormTitle').textContent = ftthT('users.edit');
                document.getElementById('ftthUserName').value = user.username;
                document.getElementById('ftthUserPass').value = '';
                document.getElementById('ftthUserRole').value = user.role;
                ftthUserFillPerms(user.permissions, user.role);
            } else {
                ftthUsersEditId = null;
                document.getElementById('ftthUserFormTitle').textContent = ftthT('users.add');
                document.getElementById('ftthUserName').value = '';
                document.getElementById('ftthUserPass').value = '';
                document.getElementById('ftthUserRole').value = 'sales';
                ftthUserFillPerms({edit_map:true, sync_mikrotik:true, sync_olt:true, sync_genieacs:true, ganti_wifi:true, import_export:true, panel_ftth:true});
            }
        };
        window.ftthUserBackToList = function() {
            var listCard = document.getElementById('ftthUsersCard');
            var formCard = document.getElementById('ftthUserFormCard');
            document.getElementById('ftthUserFormBackdrop').hidden = true;
            document.getElementById('ftthUsersBackdrop').hidden = false;
            listCard.style.left = formCard.style.left;
            listCard.style.top = formCard.style.top;
            listCard.style.transform = 'none';
            listCard._dragged = formCard._dragged;
            positionCardBelow(listCard, document.querySelector('.ftth-ac-users'));
            loadUsers();
        };
        window.ftthUserEdit = function(id) {
            var u = ftthUsersData.find(function(x) { return x.id === id; });
            if (u) ftthUserShowForm(u);
        };
        window.ftthUserDelete = function(id) {
            if (!confirm(ftthT('confirm.hapus_user'))) return;
            fetch('/noc/features/map/users/delete', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ id: id })
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.ok) { ftthUsersData = d.users || []; renderUsers(); ftthToast(ftthT('toast.user_deleted'), 'ok'); }
                else ftthToast(d.error || ftthT('toast.user_delete_fail'), 'error');
            }).catch(function() {
                ftthToast(ftthT('toast.user_delete_fail'), 'error');
            });
        };
        window.ftthUserSave = function() {
            var payload = {
                username: document.getElementById('ftthUserName').value.trim(),
                password: document.getElementById('ftthUserPass').value,
                role: document.getElementById('ftthUserRole').value,
                permissions: {
                    edit_map: document.getElementById('ftthPermEditMap').checked,
                    sync_mikrotik: document.getElementById('ftthPermMikrotik').checked,
                    sync_olt: document.getElementById('ftthPermOlt').checked,
                    sync_genieacs: document.getElementById('ftthPermGenieacs').checked,
                    ganti_wifi: document.getElementById('ftthPermWifi').checked,
                    import_export: document.getElementById('ftthPermExcel').checked,
                    panel_ftth: document.getElementById('ftthPermPanel').checked
                }
            };
            if (ftthUsersEditId) payload.id = ftthUsersEditId;
            fetch('/noc/features/map/users/save', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify(payload)
            }).then(function(r) { return r.json(); }).then(function(d) {
                if (d.ok) { ftthToast(ftthT('toast.user_saved'), 'ok'); ftthUserBackToList(); }
                else ftthToast(d.error || ftthT('toast.user_save_fail'), 'error');
            }).catch(function() {
                ftthToast(ftthT('toast.user_save_fail'), 'error');
            });
        };

        loadUsers();

        ['visRouter', 'visOdc', 'visOdp', 'visOtb', 'visClosure', 'visOnuOnline', 'visOnuOffline',
            'visOnuText', 'visCable',
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

        map.on('move zoom', function() {
            if (ftthCardDocked && !ftthCardDragging) ftthPositionDetailCard();
        });

        var searchMarker = null;
        var toastTimer = null;

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
            /* Ikon tangan menunjuk tepat ke titik koordinat (beranimasi dari atas ke bawah) */
            var handIcon = L.divIcon({
                className: 'ftth-search-hand',
                html: '<div class="ftth-search-hand-i"><i class="fa-solid fa-hand-point-down"></i></div>',
                iconSize: [36, 46],
                iconAnchor: [18, 42],
                popupAnchor: [0, -34]
            });
            var mk = L.marker([lat, lng], { icon: handIcon }).addTo(map).bindPopup(label);
            searchMarker = mk;
            ftthToast(ftthT('common.location_found'), 'ok');
            /* Terbang gaya Google Earth: menjauh ke level "negara" lalu menukik mulus ke titik.
               Popup dibuka saat mendarat agar tidak mengganggu animasi. */
            ftthEarthFly(L.latLng(lat, lng), Math.max(map.getZoom(), 16), function() {
                if (map.hasLayer(mk)) { try { mk.openPopup(); } catch (e) {} }
            });
        }

        /* Terbang sinematik ala Google Earth dengan pendekatan DARI SAMPING:
           fase 1 = meluncur mendekat lalu turun ke titik tetangga di samping target (zoom menengah),
           fase 2 = disambung TANPA JEDA menukik ke titik tujuan.
           Fase 2 dijadwalkan menjelang akhir fase 1 sehingga gerak kamera tidak pernah berhenti.
           Semua fase memakai .ftth-flying/.ftth-cinematic agar mulus tanpa jank.
           Penerbangan baru otomatis membatalkan yang lama; klik/drag peta membatalkan manual. */
        function ftthEarthFly(target, targetZoom, onLanded) {
            try { map.stop(); } catch (e) {}
            if (ftthEarthFly._abort) { try { ftthEarthFly._abort(); } catch (e) {} }
            var cont = map.getContainer();
            cont.classList.add('ftth-flying');
            cont.classList.add('ftth-cinematic');

            var d1 = 1.3, d2 = 1.9;
            /* Titik awal pendekatan: ±0.5–0.7 km di samping target */
            var side = [target.lat + 0.0045, target.lng - 0.007];
            var swingZoom = Math.max(11, Math.min(14, targetZoom - 5));
            var guard = null, dead = false;

            function cleanup() {
                map.off('moveend', landed);
                if (guard) { clearTimeout(guard); guard = null; }
                cont.classList.remove('ftth-flying');
                cont.classList.remove('ftth-cinematic');
                cont.removeEventListener('mousedown', onDown);
            }
            function finish(landedOk) {
                if (dead) return;
                dead = true;
                if (ftthEarthFly._abort === abort) ftthEarthFly._abort = null;
                cleanup();
                if (landedOk && onLanded) onLanded();
            }
            function abort() { finish(false); } /* pembatalan oleh penerbangan baru: tanpa onLanded */
            function onDown() { finish(true); }
            function landed() {
                if (guard) { clearTimeout(guard); guard = null; }
                finish(true);
            }
            /* Fase 2: menukik ke titik — dipicu timer overlap agar menyambung fase 1 tanpa jeda */
            function dive() {
                if (dead) return;
                if (guard) { clearTimeout(guard); guard = null; }
                map.once('moveend', landed);
                guard = setTimeout(landed, d2 * 1000 + 900); /* pengaman bila moveend tak terpicu */
                map.flyTo([target.lat, target.lng], targetZoom, { duration: d2 });
            }
            /* Fase 1: luncur dari posisi sekarang ke samping target sambil turun ke zoom menengah */
            function swing() {
                guard = setTimeout(dive, d1 * 1000 * 0.65); /* overlap: mulai menukik sebelum fase 1 tuntas */
                map.flyTo(side, swingZoom, { duration: d1 });
            }

            ftthEarthFly._abort = abort;
            cont.addEventListener('mousedown', onDown);
            swing();
        }

        window.ftthSearch = function() {
            closeSuggest();
            var input = document.getElementById('ftthSearchInput');
            var q = input ? input.value.trim() : '';
            if (!q) {
                ftthToast(ftthT('toast.type_first'), 'warn');
                if (input) input.focus();
                return;
            }

            var coords = parseCoords(q);
            if (coords) {
                gotoPoint(coords[0], coords[1], ftthT('search.goto_label') + ' ' + coords[0] + ', ' + coords[1]);
                return;
            }

                ftthToast(ftthT('toast.searching_for') + ' "' + q + '"...', 'info');
            fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json' }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data || !data.length) {
                    ftthToast(ftthT('toast.not_found'), 'error');
                    return;
                }
                var r = data[0];
                gotoPoint(parseFloat(r.lat), parseFloat(r.lon), r.display_name);
            })
            .catch(function() {
                ftthToast(ftthT('toast.search_fail'), 'error');
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
                ftthToast(ftthT('toast.no_coords') + ' "' + d.label.slice(0, 35) + '"', 'warn');
                return;
            }
            gotoPoint(d.lat, d.lon, d.label);
        }

        function renderSuggest(list) {
            suggestData = list || [];
            suggestActive = -1;
            if (!suggestData.length) {
                suggestEl.innerHTML = '<div class="ftth-sug-empty">' + ftthT('msg.tidak_ada_hasil') + '</div>';
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

        function ftthLoaderHtml(msg) {
            msg = msg || 'Memuat...';
            return '<div style="text-align:center;padding:18px 0"><div class="ftth-a-loader"><svg viewBox="-2 -2 58 52"><path class="ftth-a-chevron" d="M6 38 L26 8 L46 38"/><g class="ftth-a-check-group"><path class="ftth-a-check" d="M22 26 C10 30 16 44 28 34 C36 26 42 20 44 19"/><circle class="ftth-a-tip" cx="50" cy="17" r="2.5"/></g></svg></div><div style="font-size:12px;color:#a78bfa">' + msg + '</div></div>';
        }

        function ftthLoaderTiny() {
            return '<span class="ftth-a-loader" style="display:inline-block;width:14px;height:14px;vertical-align:middle;margin:0 4px 0 0"><svg viewBox="-2 -2 58 52" style="width:100%;height:100%"><path class="ftth-a-chevron" d="M6 38 L26 8 L46 38"/><g class="ftth-a-check-group"><path class="ftth-a-check" d="M22 26 C10 30 16 44 28 34 C36 26 42 20 44 19"/><circle class="ftth-a-tip" cx="50" cy="17" r="2.5"/></g></svg></span>';
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
            s.innerHTML = (spin ? ftthLoaderTiny() : '') + escapeHtml(msg);
        }

        function positionCardBelow(card, btn) {
            if (!card) return;
            if (card._dragged) return;
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
            ftthCloseAllCards();
            var bd = document.getElementById('ftthMikrotikBackdrop');
            bd.hidden = false;
            positionCardBelow(document.getElementById('ftthMikrotikCard'), document.querySelector('.ftth-ac-mikrotik'));
            loadRouterList();
            ftthMtWanOpen();
        };

        window.ftthCloseMikrotik = function() {
            document.getElementById('ftthMikrotikBackdrop').hidden = true;
            ftthMtWanClose();
        };

        /* ── Grafik live trafik WAN-ISP (card Sync Mikrotik) ──
           Canvas sparkline murni (tanpa Chart.js) agar tidak terantre
           di belakang sync yang lama; fetch pakai timeout/abort. */
        var ftthMtWanTimer = null;
        var ftthMtWanPrev = null;
        var ftthMtWanCtl = null;
        var ftthMtWanRxPts = [];
        var ftthMtWanTxPts = [];
        var FTTH_MT_WAN_MAX_PTS = 40;
        var ftthMtWanTickCount = 0;
        var ftthMtWanVisible = false;

        /* Sparkline canvas bersama untuk grafik trafik (WAN Mikrotik & PON OLT) */
        function ftthSparklineDraw(cv, rxPts, txPts) {
            if (!cv || !cv.getContext) return;
            var dpr = window.devicePixelRatio || 1;
            var w = cv.clientWidth || 240, h = cv.clientHeight || 62;
            if (cv.width !== Math.round(w * dpr) || cv.height !== Math.round(h * dpr)) {
                cv.width = Math.round(w * dpr);
                cv.height = Math.round(h * dpr);
            }
            var ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, w, h);
            var max = 0;
            Array.prototype.forEach.call(rxPts.concat(txPts), function(v) { if (v > max) max = v; });
            if (max <= 0) max = 1000;
            ctx.strokeStyle = 'rgba(148,163,184,0.15)';
            ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(0, Math.round(h / 2)); ctx.lineTo(w, Math.round(h / 2)); ctx.stroke();
            var series = [
                { pts: rxPts, line: '#22c55e', fill: 'rgba(34,197,94,0.18)' },
                { pts: txPts, line: '#3b82f6', fill: 'rgba(59,130,246,0.18)' }
            ];
            for (var s = 0; s < series.length; s++) {
                var pts = series[s].pts;
                if (!pts.length) continue;
                ctx.beginPath();
                for (var j = 0; j < pts.length; j++) {
                    var x = pts.length > 1 ? (j / (pts.length - 1)) * w : w;
                    var y = h - 2 - (Math.min(1, pts[j] / max) * (h - 6));
                    if (j === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
                }
                ctx.strokeStyle = series[s].line;
                ctx.lineWidth = 2;
                ctx.lineJoin = 'round';
                ctx.stroke();
                ctx.lineTo(w, h); ctx.lineTo(0, h); ctx.closePath();
                ctx.fillStyle = series[s].fill;
                ctx.fill();
            }
        }

        function ftthMtWanDraw() {
            ftthSparklineDraw(document.getElementById('ftthMtWanChart'), ftthMtWanRxPts, ftthMtWanTxPts);
        }

        function ftthMtWanTick() {
            /* Request sebelumnya masih berjalan (server sibuk sync) — biarkan
               selesai, jangan di-abort agar tidak bolak-balang batal */
            if (ftthMtWanCtl) return;
            var statusEl = document.getElementById('ftthMtWanStatus');
            var setStatus = function(txt, cls) {
                if (!statusEl) return;
                statusEl.textContent = txt || '';
                statusEl.className = 'ftth-mt-wan-status' + (cls ? ' ' + cls : '');
            };
            ftthMtWanCtl = ('AbortController' in window) ? new AbortController() : null;
            var ctl = ftthMtWanCtl;
            ftthMtWanTickCount++;
            var to = ctl ? setTimeout(function() { try { ctl.abort(); } catch (e) {} }, 8000) : null;
            fetch('/noc/features/map/mikrotik/wan-traffic', {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                signal: ctl ? ctl.signal : undefined
            }).then(function(res) { return res.json(); }).then(function(d) {
                clearTimeout(to);
                ftthMtWanCtl = null;
                var h = (d && d.ok && Array.isArray(d.history)) ? d.history : [];
                if (!h.length) {
                    if (ftthMtWanVisible) {
                        setStatus('Router offline / tidak terbaca', 'off');
                        document.getElementById('ftthMtWanRx').textContent = '-';
                        document.getElementById('ftthMtWanTx').textContent = '-';
                    }

                    return;
                }
                /* Laju dihitung dari selisih counter antar sampel riwayat server:
                   satu respons langsung mengisi seluruh buffer grafik */
                var rx = [], tx = [];
                for (var i = 1; i < h.length; i++) {
                    var dt = h[i].t - h[i - 1].t;
                    if (dt <= 0 || h[i].in < h[i - 1].in || h[i].out < h[i - 1].out) continue;
                    rx.push(Math.max(0, ((h[i].in - h[i - 1].in) / dt) * 8));
                    tx.push(Math.max(0, ((h[i].out - h[i - 1].out) / dt) * 8));
                }
                ftthMtWanRxPts = rx.slice(-FTTH_MT_WAN_MAX_PTS);
                ftthMtWanTxPts = tx.slice(-FTTH_MT_WAN_MAX_PTS);
                if (!ftthMtWanVisible) return;

                var lastRx = rx.length ? rx[rx.length - 1] : null;
                var lastTx = tx.length ? tx[tx.length - 1] : null;
                document.getElementById('ftthMtWanRx').textContent = lastRx === null ? '-' : ftthHumanRate(lastRx);
                document.getElementById('ftthMtWanTx').textContent = lastTx === null ? '-' : ftthHumanRate(lastTx);
                var nameEl = document.getElementById('ftthMtWanName');
                if (nameEl && d.wan) nameEl.textContent = d.wan;
                setStatus('Live · ' + (d.router_name || '') + (d.wan ? ' · ' + d.wan : ''), 'live');
                ftthMtWanDraw();
            }).catch(function() {
                clearTimeout(to);
                ftthMtWanCtl = null;
                if (ftthMtWanVisible) setStatus('Menunggu data trafik WAN…', '');
            });
        }

        /* Mode latar: polling jalan sejak halaman dibuka agar buffer grafik
           sudah terisi — begitu card Sync Mikrotik dibuka, grafik langsung
           tampil & bergerak tanpa menunggu dua poll pertama */
        function ftthMtWanStart(visible) {
            ftthMtWanStop(true);
            ftthMtWanVisible = !!visible;
            ftthMtWanTick();
            ftthMtWanTimer = setInterval(ftthMtWanTick, ftthMtWanVisible ? 2000 : 5000);
        }

        function ftthMtWanOpen() {
            ftthMtWanVisible = true;
            clearInterval(ftthMtWanTimer);
            if (ftthMtWanCtl) { /* poll latar masih jalan — interval saja, tick berikutnya otomatis */ }
            else ftthMtWanTick();
            ftthMtWanTimer = setInterval(ftthMtWanTick, 2000);
            ftthMtWanDraw();
        }

        function ftthMtWanClose() {
            /* Card ditutup: tetap sampling di latar (lebih jarang), data tidak direset */
            ftthMtWanVisible = false;
            clearInterval(ftthMtWanTimer);
            ftthMtWanTimer = setInterval(ftthMtWanTick, 5000);
        }

        function ftthMtWanStop(keepData) {
            if (ftthMtWanTimer) { clearInterval(ftthMtWanTimer); ftthMtWanTimer = null; }
            try { if (ftthMtWanCtl) ftthMtWanCtl.abort(); } catch (e) {}
            ftthMtWanCtl = null;
            if (keepData) return;
            ftthMtWanPrev = null;
            ftthMtWanRxPts = [];
            ftthMtWanTxPts = [];
            var rxVal = document.getElementById('ftthMtWanRx');
            var txVal = document.getElementById('ftthMtWanTx');
            var nameEl = document.getElementById('ftthMtWanName');
            var statusEl = document.getElementById('ftthMtWanStatus');
            if (rxVal) rxVal.textContent = '-';
            if (txVal) txVal.textContent = '-';
            if (nameEl) nameEl.textContent = '-';
            if (statusEl) { statusEl.textContent = ''; statusEl.className = 'ftth-mt-wan-status'; }
            var cv = document.getElementById('ftthMtWanChart');
            if (cv && cv.getContext) {
                var c2 = cv.getContext('2d');
                c2.setTransform(1, 0, 0, 1, 0, 0);
                c2.clearRect(0, 0, cv.width, cv.height);
            }
        }

        /* ── Grafik live trafik PON 1 OLT (card Sync OLT) — pola sama dengan WAN Mikrotik ── */
        var ftthOltPonTimer = null;
        var ftthOltPonPrev = null;
        var ftthOltPonCtl = null;
        var ftthOltPonRxPts = [];
        var ftthOltPonTxPts = [];
        var FTTH_OLT_PON_MAX_PTS = 40;
        var FTTH_OLT_PON_TICKS = 0;
        var ftthOltPonVisible = false;

        function ftthOltPonTick() {
            if (ftthOltPonCtl) return; /* request sebelumnya masih berjalan */
            var statusEl = document.getElementById('ftthOltPonStatus');
            var setStatus = function(txt, cls) {
                if (!statusEl) return;
                statusEl.textContent = txt || '';
                statusEl.className = 'ftth-mt-wan-status' + (cls ? ' ' + cls : '');
            };
            ftthOltPonCtl = ('AbortController' in window) ? new AbortController() : null;
            var ctl = ftthOltPonCtl;
            FTTH_OLT_PON_TICKS++;
            /* SSH ke OLT lebih lambat: beri timeout lebih panjang dari WAN Mikrotik.
               Respons tetap instan dari cache riwayat; poll live hanya di server
               saat sampel terakhir sudah tua */
            var to = ctl ? setTimeout(function() { try { ctl.abort(); } catch (e) {} }, 12000) : null;
            fetch('/noc/features/map/olt/pon-traffic', {
                method: 'GET',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                signal: ctl ? ctl.signal : undefined
            }).then(function(res) { return res.json(); }).then(function(d) {
                clearTimeout(to);
                ftthOltPonCtl = null;
                var h = (d && d.ok && Array.isArray(d.history)) ? d.history : [];
                if (!h.length) {
                    if (ftthOltPonVisible) {
                        setStatus(d && d.error ? d.error : 'Counter PON tidak terbaca', 'off');
                        document.getElementById('ftthOltPonRx').textContent = '-';
                        document.getElementById('ftthOltPonTx').textContent = '-';
                    }

                    return;
                }
                var rx = [], tx = [];
                for (var i = 1; i < h.length; i++) {
                    var dt = h[i].t - h[i - 1].t;
                    if (dt <= 0 || h[i].in < h[i - 1].in || h[i].out < h[i - 1].out) continue;
                    rx.push(Math.max(0, ((h[i].in - h[i - 1].in) / dt) * 8));
                    tx.push(Math.max(0, ((h[i].out - h[i - 1].out) / dt) * 8));
                }
                ftthOltPonRxPts = rx.slice(-FTTH_OLT_PON_MAX_PTS);
                ftthOltPonTxPts = tx.slice(-FTTH_OLT_PON_MAX_PTS);
                if (!ftthOltPonVisible) return;

                var lastRx = rx.length ? rx[rx.length - 1] : null;
                var lastTx = tx.length ? tx[tx.length - 1] : null;
                document.getElementById('ftthOltPonRx').textContent = lastRx === null ? '-' : ftthHumanRate(lastRx);
                document.getElementById('ftthOltPonTx').textContent = lastTx === null ? '-' : ftthHumanRate(lastTx);
                setStatus('Live · ' + (d.olt_name || 'OLT') + ' · PON ' + (d.pon || 1), 'live');
                ftthSparklineDraw(document.getElementById('ftthOltPonChart'), ftthOltPonRxPts, ftthOltPonTxPts);
            }).catch(function() {
                clearTimeout(to);
                ftthOltPonCtl = null;
                if (ftthOltPonVisible) setStatus('Menunggu data trafik PON…', '');
            });
        }

        /* Mode latar: sampling PON mulai dari load halaman (SSH lambat —
           makin awal dimulai, makin siap grafik saat card OLT dibuka) */
        function ftthOltPonStart(visible) {
            ftthOltPonStop(true);
            ftthOltPonVisible = !!visible;
            ftthOltPonTick();
            ftthOltPonTimer = setInterval(ftthOltPonTick, ftthOltPonVisible ? 3000 : 8000);
        }

        function ftthOltPonOpen() {
            ftthOltPonVisible = true;
            clearInterval(ftthOltPonTimer);
            if (!ftthOltPonCtl) ftthOltPonTick();
            ftthOltPonTimer = setInterval(ftthOltPonTick, 3000);
            ftthSparklineDraw(document.getElementById('ftthOltPonChart'), ftthOltPonRxPts, ftthOltPonTxPts);
        }

        function ftthOltPonClose() {
            /* Card ditutup: tetap sampling di latar (lebih jarang), data tidak direset */
            ftthOltPonVisible = false;
            clearInterval(ftthOltPonTimer);
            ftthOltPonTimer = setInterval(ftthOltPonTick, 8000);
        }

        function ftthOltPonStop(keepData) {
            if (ftthOltPonTimer) { clearInterval(ftthOltPonTimer); ftthOltPonTimer = null; }
            try { if (ftthOltPonCtl) ftthOltPonCtl.abort(); } catch (e) {}
            ftthOltPonCtl = null;
            if (keepData) return;
            ftthOltPonPrev = null;
            ftthOltPonRxPts = [];
            ftthOltPonTxPts = [];
            var rxVal = document.getElementById('ftthOltPonRx');
            var txVal = document.getElementById('ftthOltPonTx');
            var statusEl = document.getElementById('ftthOltPonStatus');
            if (rxVal) rxVal.textContent = '-';
            if (txVal) txVal.textContent = '-';
            if (statusEl) { statusEl.textContent = ''; statusEl.className = 'ftth-mt-wan-status'; }
            var cv = document.getElementById('ftthOltPonChart');
            if (cv && cv.getContext) {
                var c3 = cv.getContext('2d');
                c3.setTransform(1, 0, 0, 1, 0, 0);
                c3.clearRect(0, 0, cv.width, cv.height);
            }
        }

        document.querySelectorAll('.ftth-modal-card').forEach(function(card) {
            var head = card.querySelector('.ftth-modal-head');
            var isCalc = card.classList.contains('ftth-calc-card');
            var dg = false, started = false, ox = 0, oy = 0, L = 0, T = 0;
            var rafId = null, pendingX = 0, pendingY = 0;
            head.addEventListener('mousedown', function(e) {
                if (e.target.closest('.ftth-modal-close')) return;
                if (e.target.closest('.ftth-onu-table-tools')) return;
                if (e.target.closest('.ftth-browse-back')) return;
                if (e.target.closest('.ftth-calc-mode-btn')) return;
                if (e.target.closest('.ftth-calc-kuping')) return;
                dg = true;
                started = false;
                ox = e.clientX;
                oy = e.clientY;
                e.preventDefault();
            });
            document.addEventListener('mousemove', function(e) {
                if (!dg) return;
                var dx = e.clientX - ox, dy = e.clientY - oy;
                if (!started) {
                    if (Math.abs(dx) < 3 && Math.abs(dy) < 3) return;
                    started = true;
                    card._dragged = true;
                    if (isCalc) {
                        var wrap = document.getElementById('ftthCalcWrap');
                        var bd = document.getElementById('ftthCalcBackdrop');
                        var wr = wrap.getBoundingClientRect();
                        L = wr.left;
                        T = wr.top;
                        wrap.style.position = 'fixed';
                        wrap.style.left = L + 'px';
                        wrap.style.top = T + 'px';
                        wrap.style.margin = '0';
                        bd.style.display = 'block';
                        bd.style.alignItems = '';
                        bd.style.justifyContent = '';
                        card.style.position = 'absolute';
                        card.style.left = '0';
                        card.style.right = '';
                        card.style.transform = 'none';
                        card.style.zIndex = '3';
                    } else {
                        var r = card.getBoundingClientRect();
                        L = r.left;
                        T = r.top;
                        card.style.position = 'fixed';
                        card.style.left = L + 'px';
                        card.style.top = T + 'px';
                        card.style.transform = 'none';
                        card.style.zIndex = '10005';
                    }
                    head.style.cursor = 'grabbing';
                }
                pendingX = L + e.clientX - ox;
                pendingY = T + e.clientY - oy;
                if (rafId) return;
                rafId = requestAnimationFrame(function() {
                    rafId = null;
                    if (isCalc) {
                        var wrap = document.getElementById('ftthCalcWrap');
                        wrap.style.transform = 'translate(' + (pendingX - L) + 'px,' + (pendingY - T) + 'px)';
                    } else {
                        card.style.left = pendingX + 'px';
                        card.style.top = pendingY + 'px';
                    }
                });
            });
            document.addEventListener('mouseup', function() {
                if (!dg) return;
                dg = false;
                started = false;
                if (rafId) { cancelAnimationFrame(rafId); rafId = null; }
                head.style.cursor = 'grab';
            });
        })

        function mtReadForm() {
            var ip = document.getElementById('mtIp').value.trim();
            var port = document.getElementById('mtPort').value.trim();
            var user = document.getElementById('mtUser').value.trim();
            var pass = document.getElementById('mtPass').value;
            if (!ip) { ftthToast(ftthT('toast.fill_ip'), 'warn'); return null; }
            if (!port || isNaN(parseInt(port, 10))) { ftthToast(ftthT('toast.fill_port'), 'warn'); return null; }
            if (!user) { ftthToast(ftthT('toast.fill_user'), 'warn'); return null; }
            if (!pass) { ftthToast(ftthT('toast.fill_pass'), 'warn'); return null; }
            return { ip: ip, port: parseInt(port, 10), username: user, password: pass };
        }

        /* Cache daftar router/OLT/config agar membuka card tidak memuat ulang
           dari awal — render instan dari cache + refresh diam-diam di belakang */
        var routerListCache = null;
        var oltListCache = null;
        var genieacsConfigCache = null;
        var genieacsSummaryCache = null;

        function renderRouterList(routers) {
            var list = document.getElementById('ftthRouterList');
            if (!routers.length) {
                list.innerHTML = '<div class="ftth-router-empty">' + ftthT('loader.router_empty') + '</div>';
                return;
            }
            routerListCache = routers;
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

        /* Skeleton kotak router/OLT/GenieACS saat memuat:
           animasi loader di kiri, teks status di sampingnya */
        function ftthRouterSkelHtml(text) {
            return '<div class="ftth-router-row ftth-router-skel"><span class="ftth-router-info">' +
                '<span class="ftth-router-line" style="display:inline-flex;align-items:center;gap:6px">' +
                    '<span class="ftth-a-loader" style="display:inline-block;width:17px;height:17px;margin:0;flex:none"><svg viewBox="-2 -2 58 52" style="width:100%;height:100%"><path class="ftth-a-chevron" d="M6 38 L26 8 L46 38"/><g class="ftth-a-check-group"><path class="ftth-a-check" d="M22 26 C10 30 16 44 28 34 C36 26 42 20 44 19"/><circle class="ftth-a-tip" cx="50" cy="17" r="2.5"/></g></svg></span>' +
                    '<span style="color:#a78bfa">' + text + '</span>' +
                '</span>' +
                '<span class="ftth-router-version">&nbsp;</span>' +
                '</span></div>';
        }

        function loadRouterList() {
            var list = document.getElementById('ftthRouterList');
            if (!list) return;
            if (routerListCache) {
                renderRouterList(routerListCache);
                refreshRouterList();
                return;
            }
            list.innerHTML = ftthRouterSkelHtml('Memuat data router…');
            mtApi('/noc/features/map/mikrotik', 'GET').then(function(r) {
                renderRouterList(r.data.routers || []);
            }).catch(function() {
                list.innerHTML = '<div class="ftth-router-empty">' + ftthT('loader.router_fail') + '</div>';
            });
        }

        function refreshRouterList() {
            mtApi('/noc/features/map/mikrotik', 'GET').then(function(r) {
                renderRouterList(r.data.routers || []);
            }).catch(function() {});
        }

        window.ftthSaveMikrotik = function() {
            if (mtBusy) return;
            var payload = mtReadForm();
            if (!payload) return;
            setMtBusy(true);
            setMtStatus(ftthT('sync.menyimpan'), 'info', true);
            mtApi('/noc/features/map/mikrotik/save', 'POST', payload).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || ftthT('toast.router_save_fail'), 'error');
                    setMtStatus(r.data.error || ftthT('sync.gagal_simpan'), 'fail');
                    return;
                }
                ftthToast(ftthT('toast.router_saved'), 'ok');
                setMtStatus(ftthT('sync.tersimpan'), 'ok');
                renderRouterList(r.data.routers || []);
            }).catch(function() {
                ftthToast(ftthT('toast.router_save_fail'), 'error');
                setMtStatus(ftthT('sync.gagal_simpan'), 'fail');
            }).then(function() { setMtBusy(false); });
        };

        window.ftthConnectMikrotik = function() {
            if (mtBusy) return;
            var payload = mtReadForm();
            if (!payload) return;
            setMtBusy(true);
            setMtStatus(ftthT('sync.menghubungkan'), 'info', true);
            mtApi('/noc/features/map/mikrotik/save', 'POST', payload).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || ftthT('toast.save_first'), 'error');
                    setMtStatus(r.data.error || ftthT('sync.konek_gagal'), 'fail');
                    return null;
                }
                return mtApi('/noc/features/map/mikrotik/connect', 'POST', { id: r.data.router.id });
            }).then(function(r) {
                if (!r) return;
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || ftthT('common.connect_fail'), 'error');
                    setMtStatus(r.data.error || ftthT('sync.konek_gagal'), 'fail');
                } else {
                    var ppp = r.data.pppoe_users;
                    var msg = ftthT('status.konek_ok') + (ppp != null ? ' — ' + ppp + ' ' + ftthT('status.user_pppoe') : (r.data.routeros_version ? ' — v' + r.data.routeros_version : ''));
                    ftthToast(msg, 'ok');
                    setMtStatus(msg, 'ok');
                    updatePppoeStats(r.data.pppoe_online, r.data.pppoe_offline, r.data.prev_pppoe_online, r.data.prev_pppoe_offline);
                }
                return loadRouterList();
            }).catch(function() {
                ftthToast(ftthT('common.connect_fail'), 'error');
                setMtStatus(ftthT('sync.konek_gagal'), 'fail');
            }).then(function() { setMtBusy(false); });
        };

        window.ftthSyncAllMikrotik = function() {
            if (mtBusy) return;
            setMtBusy(true);
            setMtStatus(ftthT('sync.menyinkronkan_semua'), 'info', true);
            mtApi('/noc/features/map/mikrotik/sync-all', 'POST', { force: true }).then(function(r) {
                if (r.data.ok != null) {
                    if (r.data.failed) {
                        ftthToast('Sync ' + r.data.ok + '/' + r.data.total + ' ' + ftthT('toast.router_synced'), 'warn');
                        setMtStatus(ftthT('sync.gagal') + ' ' + r.data.failed + '/' + r.data.total, 'fail');
                    } else {
                        ftthToast('Sync ' + r.data.ok + '/' + r.data.total + ' ' + ftthT('toast.router_synced'), 'ok');
                        setMtStatus('Sync ' + r.data.ok + '/' + r.data.total + ' ' + ftthT('status.sync_ok'), 'ok');
                    }
                    setPppoeStats(r.data.pppoe_online, r.data.pppoe_offline);
                } else {
                    ftthToast(r.data.error || ftthT('toast.sync_fail'), 'error');
                    setMtStatus(r.data.error || ftthT('sync.gagal_sync'), 'fail');
                }
                loadRouterList();
            }).catch(function() {
                ftthToast(ftthT('toast.sync_all_fail'), 'error');
                setMtStatus(ftthT('sync.gagal_sync'), 'fail');
            }).then(function() { setMtBusy(false); });
        };

        window.ftthSyncRouter = function(id) {
            if (mtBusy) return;
            setMtBusy(true);
            setMtStatus(ftthT('sync.menghubungkan'), 'info', true);
            mtApi('/noc/features/map/mikrotik/connect', 'POST', { id: id }).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || ftthT('toast.router_sync_fail'), 'error');
                    setMtStatus(r.data.error || ftthT('sync.sync_gagal'), 'fail');
                } else {
                    var ppp = r.data.pppoe_users;
                    var msg = ftthT('status.sync_ok') + (ppp != null ? ' — ' + ppp + ' ' + ftthT('status.user_pppoe') : (r.data.routeros_version ? ' — v' + r.data.routeros_version : ''));
                    ftthToast(msg, 'ok');
                    setMtStatus(msg, 'ok');
                    updatePppoeStats(r.data.pppoe_online, r.data.pppoe_offline, r.data.prev_pppoe_online, r.data.prev_pppoe_offline);
                }
                loadRouterList();
            }).catch(function() {
                ftthToast(ftthT('toast.router_sync_fail'), 'error');
                setMtStatus(ftthT('sync.sync_gagal'), 'fail');
            }).then(function() { setMtBusy(false); });
        };

        window.ftthDelRouter = function(id) {
            if (mtBusy) return;
            if (!confirm(ftthT('confirm.hapus_router'))) return;
            setMtBusy(true);
            setMtStatus(ftthT('sync.menghapus'), 'info', true);
            mtApi('/noc/features/map/mikrotik/delete', 'POST', { id: id }).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || ftthT('toast.router_delete_fail'), 'error');
                    setMtStatus(r.data.error || ftthT('sync.gagal_hapus'), 'fail');
                } else {
                    ftthToast(ftthT('toast.router_deleted'), 'ok');
                    setMtStatus(ftthT('sync.router_dihapus'), 'ok');
                }
                renderRouterList((r.data.routers || []).length ? r.data.routers : []);
            }).catch(function() {
                ftthToast(ftthT('toast.router_delete_fail'), 'error');
                setMtStatus(ftthT('sync.gagal_hapus'), 'fail');
            }).then(function() { setMtBusy(false); });
        };

        /* ── Sync OLT ── */

        function setOltStatus(msg, type, spin) {
            var s = document.getElementById('ftthOltStatus');
            if (!s) return;
            s.className = 'ftth-mt-status ' + (type || 'info');
            s.innerHTML = (spin ? ftthLoaderTiny() : '') + escapeHtml(msg);
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
            ftthCloseAllCards();
            var bd = document.getElementById('ftthOltBackdrop');
            bd.hidden = false;
            positionCardBelow(document.getElementById('ftthOltCard'), document.querySelector('.ftth-ac-olt'));
            loadOltList();
            ftthOltPonOpen();
        };

        window.ftthCloseOlt = function() {
            document.getElementById('ftthOltBackdrop').hidden = true;
            ftthOltPonClose();
        };

        function oltReadForm() {
            var ip = document.getElementById('oltIp').value.trim();
            var port = document.getElementById('oltPort').value.trim();
            var user = document.getElementById('oltUser').value.trim();
            var pass = document.getElementById('oltPass').value;
            var brand = document.getElementById('oltBrand').value;
            if (!ip) { ftthToast(ftthT('toast.fill_olt_ip'), 'warn'); return null; }
            if (!port || isNaN(parseInt(port, 10))) { ftthToast(ftthT('toast.fill_olt_port'), 'warn'); return null; }
            if (!user) { ftthToast(ftthT('toast.fill_user'), 'warn'); return null; }
            if (!pass) { ftthToast(ftthT('toast.fill_pass'), 'warn'); return null; }
            return { ip: ip, port: parseInt(port, 10), username: user, password: pass, brand: brand };
        }

        function renderOltList(olts) {
            var list = document.getElementById('ftthOltList');
            if (!olts.length) {
                list.innerHTML = '<div class="ftth-router-empty">' + ftthT('loader.olt_empty') + '</div>';
                return;
            }
            oltListCache = olts;
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
            if (oltListCache) {
                renderOltList(oltListCache);
                refreshOltList();
                return;
            }
            list.innerHTML = ftthRouterSkelHtml('Memuat data OLT…');
            mtApi('/noc/features/map/olt', 'GET').then(function(r) {
                renderOltList(r.data.olts || []);
            }).catch(function() {
                list.innerHTML = '<div class="ftth-router-empty">' + ftthT('loader.olt_fail') + '</div>';
            });
        }

        function refreshOltList() {
            mtApi('/noc/features/map/olt', 'GET').then(function(r) {
                renderOltList(r.data.olts || []);
            }).catch(function() {});
        }

        window.ftthSaveOlt = function() {
            if (oltBusy) return;
            var payload = oltReadForm();
            if (!payload) return;
            setOltBusy(true);
            setOltStatus(ftthT('sync.menyimpan'), 'info', true);
            mtApi('/noc/features/map/olt/save', 'POST', payload).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || ftthT('toast.olt_save_fail'), 'error');
                    setOltStatus(r.data.error || ftthT('sync.gagal_simpan'), 'fail');
                    return;
                }
                ftthToast(ftthT('toast.olt_saved'), 'ok');
                setOltStatus(ftthT('sync.tersimpan'), 'ok');
                renderOltList(r.data.olts || []);
            }).catch(function() {
                ftthToast(ftthT('toast.olt_save_fail'), 'error');
                setOltStatus(ftthT('sync.gagal_simpan'), 'fail');
            }).then(function() { setOltBusy(false); });
        };

        window.ftthConnectOlt = function() {
            if (oltBusy) return;
            var payload = oltReadForm();
            if (!payload) return;
            setOltBusy(true);
            setOltStatus(ftthT('sync.menghubungkan'), 'info', true);
            mtApi('/noc/features/map/olt/save', 'POST', payload).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || ftthT('toast.save_olt_first'), 'error');
                    setOltStatus(r.data.error || ftthT('sync.konek_gagal'), 'fail');
                    return null;
                }
                return mtApi('/noc/features/map/olt/connect', 'POST', { id: r.data.olt.id });
            }).then(function(r) {
                if (!r) return;
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || ftthT('common.connect_fail'), 'error');
                    setOltStatus(r.data.error || ftthT('sync.konek_gagal'), 'fail');
                } else {
                    var onu = r.data.onu_total;
                    var msg = ftthT('status.konek_ok') + (onu != null ? ' — ' + onu + ' ' + ftthT('status.onu') : '');
                    ftthToast(msg, 'ok');
                    setOltStatus(msg, 'ok');
                    updateOnuStats(r.data.onu_online, r.data.onu_offline);
                }
                return loadOltList();
            }).catch(function() {
                ftthToast(ftthT('common.connect_fail'), 'error');
                setOltStatus(ftthT('sync.konek_gagal'), 'fail');
            }).then(function() { setOltBusy(false); });
        };

        window.ftthSyncAllOlt = function() {
            if (oltBusy) return;
            setOltBusy(true);
            setOltStatus(ftthT('sync.menyinkronkan_semua'), 'info', true);
            mtApi('/noc/features/map/olt/sync-all', 'POST', { force: true }).then(function(r) {
                if (r.data.ok != null) {
                    if (r.data.failed) {
                        ftthToast('Sync ' + r.data.ok + '/' + r.data.total + ' ' + ftthT('toast.olt_synced'), 'warn');
                        setOltStatus(ftthT('sync.gagal') + ' ' + r.data.failed + '/' + r.data.total, 'fail');
                    } else {
                        ftthToast('Sync ' + r.data.ok + '/' + r.data.total + ' ' + ftthT('toast.olt_synced'), 'ok');
                        setOltStatus('Sync ' + r.data.ok + '/' + r.data.total + ' ' + ftthT('status.sync_ok'), 'ok');
                    }
                } else {
                    ftthToast(r.data.error || ftthT('toast.sync_fail'), 'error');
                    setOltStatus(r.data.error || ftthT('sync.gagal_sync'), 'fail');
                }
                loadOltList();
            }).catch(function() {
                ftthToast(ftthT('toast.olt_sync_all_fail'), 'error');
                setOltStatus(ftthT('sync.gagal_sync'), 'fail');
            }).then(function() { setOltBusy(false); });
        };

        window.ftthSyncOlt = function(id) {
            if (oltBusy) return;
            setOltBusy(true);
            setOltStatus(ftthT('sync.menghubungkan'), 'info', true);
            mtApi('/noc/features/map/olt/connect', 'POST', { id: id }).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || ftthT('toast.olt_sync_fail'), 'error');
                    setOltStatus(r.data.error || ftthT('sync.sync_gagal'), 'fail');
                } else {
                    var onu = r.data.onu_total;
                    var msg = ftthT('status.sync_ok') + (onu != null ? ' — ' + onu + ' ' + ftthT('status.onu') : '');
                    ftthToast(msg, 'ok');
                    setOltStatus(msg, 'ok');
                    updateOnuStats(r.data.onu_online, r.data.onu_offline);
                }
                loadOltList();
            }).catch(function() {
                ftthToast(ftthT('toast.olt_sync_fail'), 'error');
                setOltStatus(ftthT('sync.sync_gagal'), 'fail');
            }).then(function() { setOltBusy(false); });
        };

        window.ftthDelOlt = function(id) {
            if (oltBusy) return;
            if (!confirm(ftthT('confirm.hapus_olt'))) return;
            setOltBusy(true);
            setOltStatus(ftthT('sync.menghapus'), 'info', true);
            mtApi('/noc/features/map/olt/delete', 'POST', { id: id }).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || ftthT('toast.olt_delete_fail'), 'error');
                    setOltStatus(r.data.error || ftthT('sync.gagal_hapus'), 'fail');
                } else {
                    ftthToast(ftthT('toast.olt_deleted'), 'ok');
                    setOltStatus(ftthT('sync.olt_dihapus'), 'ok');
                }
                renderOltList((r.data.olts || []).length ? r.data.olts : []);
            }).catch(function() {
                ftthToast(ftthT('toast.olt_delete_fail'), 'error');
                setOltStatus(ftthT('sync.gagal_hapus'), 'fail');
            }).then(function() { setOltBusy(false); });
        };

        /* ── Sync GenieACS ── */

        function setGenieacsStatus(msg, type, spin) {
            var s = document.getElementById('ftthGenieacsStatus');
            if (!s) return;
            s.className = 'ftth-mt-status ' + (type || 'info');
            s.innerHTML = (spin ? ftthLoaderTiny() : '') + escapeHtml(msg);
        }

        window.ftthOpenGenieacs = function() {
            ftthCloseAllCards();
            var bd = document.getElementById('ftthGenieacsBackdrop');
            bd.hidden = false;
            positionCardBelow(document.getElementById('ftthGenieacsCard'), document.querySelector('.ftth-ac-genieacs'));
            loadGenieacsConfig();
            if (genieacsSummaryCache) {
                renderGenieacsSummary(genieacsSummaryCache);
            } else {
                document.getElementById('ftthGenieacsSummary').innerHTML = ftthRouterSkelHtml('Memuat data GenieACS…');
            }
        };

        window.ftthCloseGenieacs = function() {
            document.getElementById('ftthGenieacsBackdrop').hidden = true;
        };

        function loadGenieacsConfig() {
            var input = document.getElementById('genieacsUrl');
            if (genieacsConfigCache) {
                if (input && genieacsConfigCache.base_url) input.value = genieacsConfigCache.base_url;
                refreshGenieacsConfig();
                return;
            }
            mtApi('/noc/features/map/genieacs', 'GET').then(function(r) {
                genieacsConfigCache = r.data || {};
                if (input && r.data.base_url) input.value = r.data.base_url;
            }).catch(function() {});
        }

        function refreshGenieacsConfig() {
            var input = document.getElementById('genieacsUrl');
            mtApi('/noc/features/map/genieacs', 'GET').then(function(r) {
                genieacsConfigCache = r.data || {};
                if (input && r.data.base_url) input.value = r.data.base_url;
            }).catch(function() {});
        }

        window.ftthSaveGenieacsConfig = function() {
            var url = document.getElementById('genieacsUrl').value.trim();
            setGenieacsStatus(ftthT('sync.menyimpan'), 'info', true);
            mtApi('/noc/features/map/genieacs/save', 'POST', { url: url }).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || ftthT('toast.config_save_fail'), 'error');
                    setGenieacsStatus(r.data.error || ftthT('sync.gagal_simpan'), 'fail');
                } else {
                    ftthToast(r.data.message || ftthT('toast.config_saved'), 'ok');
                    setGenieacsStatus(ftthT('sync.config_tersimpan'), 'ok');
                }
            }).catch(function() {
                ftthToast(ftthT('toast.config_save_fail'), 'error');
                setGenieacsStatus(ftthT('sync.gagal_simpan'), 'fail');
            });
        };

        window.ftthSyncGenieacsDevices = function() {
            setGenieacsStatus(ftthT('sync.menyinkronkan'), 'info', true);
            mtApi('/noc/features/map/genieacs/sync', 'POST').then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || ftthT('toast.genieacs_sync_fail'), 'error');
                    setGenieacsStatus(r.data.error || ftthT('sync.gagal_sync'), 'fail');
                } else {
                    var msg = r.data.message || (r.data.total + ' device');
                    ftthToast(msg, 'ok');
                    setGenieacsStatus(r.data.online + ' online · ' + r.data.offline + ' offline', 'ok');
                }
                renderGenieacsSummary(r.data);
            }).catch(function() {
                ftthToast(ftthT('toast.genieacs_sync_fail'), 'error');
                setGenieacsStatus(ftthT('sync.gagal_sync'), 'fail');
            });
        };

        function renderGenieacsSummary(d) {
            var el = document.getElementById('ftthGenieacsSummary');
            if (!el) return;
            if (!d || !d.ok) {
                el.innerHTML = '<div class="ftth-router-empty" style="color:#f87171;font-weight:600">' + ftthT('loader.sync_empty') + '</div>';
                return;
            }
            genieacsSummaryCache = d;
            el.innerHTML =
                '<div class="ftth-router-row"><span class="ftth-router-info">' +
                    '<span class="ftth-router-line"><span class="dot" style="background:#22c55e"></span> ' + ftthT('status.online') + '</span>' +
                    '<span class="ftth-router-version">' + ftthT('status.genieacs_active') + '</span>' +
                '</span><b style="color:#4ade80">' + (d.online || 0) + '</b></div>' +
                '<div class="ftth-router-row"><span class="ftth-router-info">' +
                    '<span class="ftth-router-line"><span class="dot" style="background:#ef4444"></span> ' + ftthT('status.offline') + '</span>' +
                    '<span class="ftth-router-version">' + ftthT('status.genieacs_inactive') + '</span>' +
                '</span><b style="color:#f87171">' + (d.offline || 0) + '</b></div>' +
                '<div class="ftth-router-row"><span class="ftth-router-info">' +
                    '<span class="ftth-router-line">' + ftthT('status.total_device') + '</span>' +
                    '<span class="ftth-router-version">' + (d.updated || 0) + ' ' + ftthT('status.onu_tersambung') + '</span>' +
                '</span><b style="color:#93c5fd">' + (d.total || 0) + '</b></div>';
        }

        /* ── Notifikasi (WhatsApp & Telegram) ── */

        var notifConfigCache = null;

        function setNotifStatus(id, msg, type, spin) {
            var s = document.getElementById(id);
            if (!s) return;
            s.className = 'ftth-mt-status ' + (type || 'info');
            s.innerHTML = (spin ? ftthLoaderTiny() : '') + escapeHtml(msg);
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
            ftthCloseAllCards();
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
            ftthCloseAllCards();
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
            setNotifStatus('ftthNotifWaStatus', ftthT('sync.menyimpan'), 'info', true);
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
                    var err = r.data.error || ftthT('sync.gagal_simpan_pengaturan');
                    ftthToast(err, 'error');
                    setNotifStatus('ftthNotifWaStatus', err, 'fail');
                } else {
                    notifConfigCache = null;
                    ftthToast(ftthT('toast.wa_saved'), 'ok');
                    setNotifStatus('ftthNotifWaStatus', ftthT('sync.tersimpan'), 'ok');
                }
            }).catch(function() {
                ftthToast(ftthT('toast.settings_save_fail'), 'error');
                setNotifStatus('ftthNotifWaStatus', ftthT('sync.gagal_simpan'), 'fail');
            });
        };

        window.ftthSaveNotifTg = function() {
            setNotifStatus('ftthNotifTgStatus', ftthT('sync.menyimpan'), 'info', true);
            mtApi('/noc/features/map/notif/save', 'POST', {
                telegram: {
                    enabled: String(document.getElementById('notifTgEnabled').checked),
                    bot_token: document.getElementById('notifTgToken').value.trim(),
                    chat_id: document.getElementById('notifTgChatId').value.trim()
                }
            }).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    var err = r.data.error || ftthT('sync.gagal_simpan_pengaturan');
                    ftthToast(err, 'error');
                    setNotifStatus('ftthNotifTgStatus', err, 'fail');
                } else {
                    notifConfigCache = null;
                    ftthToast(ftthT('toast.tg_saved'), 'ok');
                    setNotifStatus('ftthNotifTgStatus', ftthT('sync.tersimpan'), 'ok');
                }
            }).catch(function() {
                ftthToast(ftthT('toast.settings_save_fail'), 'error');
                setNotifStatus('ftthNotifTgStatus', ftthT('sync.gagal_simpan'), 'fail');
            });
        };

        /* ── Card Queue (PPPoE client) ── */

        var queueData = [];

        function setQueueStatus(msg, type, spin) {
            var s = document.getElementById('ftthQueueStatus');
            if (!s) return;
            s.className = 'ftth-mt-status ' + (type || 'info');
            s.innerHTML = (spin ? ftthLoaderTiny() : '') + escapeHtml(msg);
        }

        window.ftthOpenQueue = function() {
            ftthCloseAllCards();
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
            wrap.innerHTML = '<div class="ftth-router-empty">' + ftthLoaderHtml(ftthT('loader.pppoe')) + '</div>';
            setQueueStatus(ftthT('qstatus.loading'), 'info');
            mtApi('/noc/features/map/mikrotik/pppoe', 'GET').then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    setQueueStatus(r.data.error || ftthT('qstatus.load_fail'), 'fail');
                    wrap.innerHTML = '<div class="ftth-router-empty">' + escapeHtml(r.data.error || ftthT('loader.pppoe_fail')) + '</div>';
                    return;
                }
                queueData = r.data.clients || [];
                renderQueue();
                setQueueStatus(queueData.length + ' ' + ftthT('qstatus.pppoe_active'), 'ok');
                ftthToast(queueData.length + ' ' + ftthT('qstatus.pppoe_active'), 'ok');
            }).catch(function() {
                setQueueStatus(ftthT('qstatus.fail'), 'fail');
                wrap.innerHTML = '<div class="ftth-router-empty">' + ftthT('loader.pppoe_fail') + '</div>';
            });
        }

        function renderQueue() {
            var wrap = document.getElementById('ftthQueueWrap');
            if (!wrap) return;
            if (!queueData.length) {
                wrap.innerHTML = '<div class="ftth-router-empty">' + ftthT('loader.pppoe_empty') + '<br><small>' + ftthT('loader.pppoe_hint') + '</small></div>';
                return;
            }
            var q = ((document.getElementById('ftthQueueSearch') || {}).value || '').toLowerCase();
            var rows = [];
            queueData.forEach(function(c, i) {
                if (!q || ((c.customer_name || '') + ' ' + c.name + ' ' + c.address + ' ' + c.router_name + ' ' + c.profile + ' ' + c.comment + ' ' + (c.serial_number || '') + ' ' + (c.olt || '') + ' ' + (c.odp || '')).toLowerCase().indexOf(q) !== -1) {
                    rows.push({ c: c, i: i });
                }
            });
            if (!rows.length) {
                wrap.innerHTML = '<div class="ftth-router-empty">' + ftthT('loader.no_search') + '</div>';
                return;
            }
            wrap.innerHTML = rows.map(function(row) {
                var c = row.c;
                var disp = c.customer_name || c.name || '-';
                var rx = c.rx_power != null ? (typeof c.rx_power === 'number' ? c.rx_power.toFixed(2) : c.rx_power) + ' dBm' : null;
                var lines = '<div class="ftth-queue-item">' +
                    '<div class="ftth-queue-item-main">' +
                    '<div class="ftth-queue-item-name" title="' + escapeHtml(c.name) + '">' + escapeHtml(disp) + '</div>';
                if (c.serial_number || rx) {
                    lines += '<div class="ftth-queue-item-ip">SN: ' + escapeHtml(c.serial_number || '-') + (rx ? ' · ' + escapeHtml(rx) : '') + '</div>';
                }
                if (c.olt || c.odp) {
                    lines += '<div class="ftth-queue-item-ip">OLT: ' + escapeHtml(c.olt || '-') + (c.odp ? ' · ODP: ' + escapeHtml(c.odp) : '') + '</div>';
                }
                if (c.address) {
                    lines += '<div class="ftth-queue-item-ip">' + escapeHtml(c.address) + '</div>';
                }
                lines += '</div>' +
                    '<button type="button" class="ftth-queue-item-add" onclick="ftthQueueAdd(' + row.i + ')"><i class="fa-solid fa-plus"></i> ADD</button>' +
                    '</div>';
                return lines;
            }).join('');
        }

        window.ftthQueueAdd = function(idx) {
            var c = queueData[idx];
            if (!c) return;
            ftthOpenAddDevice({
                type: 'onu',
                name: c.customer_name || c.name || '',
                ip: c.address || '',
                pppoe: c.name || '',
                parent: c.odp || c.olt || '',
                notes: [c.comment, c.profile ? 'Profile: ' + c.profile : ''].filter(Boolean).join(' — ')
            });
        };

        var queueSearchEl = document.getElementById('ftthQueueSearch');
        if (queueSearchEl) {
            queueSearchEl.addEventListener('input', function() {
                if (queueData.length) renderQueue();
            });
        }

        /* ── Dropdown menu Queue (PPPoE / Hotspot) ── */
        window.ftthToggleQueueMenu = function(e) {
            e.stopPropagation();
            ftthCloseAllCards();
            ftthForceCloseMeasure();
            ftthForceCloseNotif();
            var m = document.getElementById('ftthQueueMenu');
            if (m) m.hidden = !m.hidden;
        };
        window.ftthCloseQueueMenu = function() {
            var m = document.getElementById('ftthQueueMenu');
            if (m) m.hidden = true;
        };
        document.addEventListener('click', function(e) {
            var m = document.getElementById('ftthQueueMenu');
            if (m && !m.hidden && !m.contains(e.target) && !(e.target.closest && e.target.closest('.ftth-ac-queue'))) {
                m.hidden = true;
            }
        });

        /* ── Card Daftar Hotspot (ONU Hotspot) ── */
        var hotspotData = [];

        function setHotspotStatus(msg, type, spin) {
            var s = document.getElementById('ftthHotspotStatus');
            if (!s) return;
            s.className = 'ftth-mt-status ' + (type || 'info');
            s.innerHTML = (spin ? ftthLoaderTiny() : '') + escapeHtml(msg);
        }

        window.ftthOpenHotspot = function() {
            ftthCloseAllCards();
            ftthCloseQueueMenu();
            var bd = document.getElementById('ftthHotspotBackdrop');
            bd.hidden = false;
            positionCardBelow(document.getElementById('ftthHotspotCard'), document.querySelector('.ftth-ac-queue'));
            loadHotspot();
        };

        window.ftthCloseHotspot = function() {
            document.getElementById('ftthHotspotBackdrop').hidden = true;
        };

        window.ftthRefreshHotspot = function() { loadHotspot(); };

        function loadHotspot() {
            var wrap = document.getElementById('ftthHotspotWrap');
            if (!wrap) return;
            wrap.innerHTML = '<div class="ftth-router-empty">' + ftthLoaderHtml(ftthT('loader.hotspot')) + '</div>';
            setHotspotStatus(ftthT('qstatus.loading'), 'info');
            mtApi('/noc/features/map/hotspot', 'GET').then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    setHotspotStatus(r.data.error || ftthT('qstatus.load_fail'), 'fail');
                    wrap.innerHTML = '<div class="ftth-router-empty">' + escapeHtml(r.data.error || ftthT('loader.hotspot_fail')) + '</div>';
                    return;
                }
                hotspotData = r.data.clients || [];
                renderHotspot();
                setHotspotStatus(hotspotData.length + ' ONU hotspot', 'ok');
            }).catch(function() {
                setHotspotStatus(ftthT('qstatus.fail'), 'fail');
                wrap.innerHTML = '<div class="ftth-router-empty">' + ftthT('loader.hotspot_fail') + '</div>';
            });
        }

        function renderHotspot() {
            var wrap = document.getElementById('ftthHotspotWrap');
            if (!wrap) return;
            if (!hotspotData.length) {
                wrap.innerHTML = '<div class="ftth-router-empty">' + ftthT('loader.hotspot_empty') + '<br><small>' + ftthT('loader.hotspot_hint') + '</small></div>';
                return;
            }
            var q = ((document.getElementById('ftthHotspotSearch') || {}).value || '').toLowerCase();
            var rows = [];
            hotspotData.forEach(function(c, i) {
                if (!q || ((c.name || '') + ' ' + (c.serial_number || '') + ' ' + (c.customer_code || '') + ' ' + (c.caller_id || '') + ' ' + (c.ip_address || '') + ' ' + (c.olt || '') + ' ' + (c.odp || '')).toLowerCase().indexOf(q) !== -1) {
                    rows.push({ c: c, i: i });
                }
            });
            if (!rows.length) {
                wrap.innerHTML = '<div class="ftth-router-empty">' + ftthT('loader.no_search') + '</div>';
                return;
            }
            wrap.innerHTML = rows.map(function(row) {
                var c = row.c;
                var statusDot = c.status === 'online' ? 'online' : (c.status === 'offline' ? 'offline' : '');
                var rx = c.rx_power != null ? (typeof c.rx_power === 'number' ? c.rx_power.toFixed(2) : c.rx_power) + ' dBm' : '-';
                return '<div class="ftth-queue-item">' +
                    '<div class="ftth-queue-item-main">' +
                    '<div class="ftth-queue-item-name" title="' + escapeHtml(c.name) + '"><span class="ftth-status-dot ' + statusDot + '"></span> ' + escapeHtml(c.name) + '</div>' +
                    '<div class="ftth-queue-item-ip">SN: ' + escapeHtml(c.serial_number || '-') + ' · ' + escapeHtml(rx) + '</div>' +
                    '<div class="ftth-queue-item-ip">OLT: ' + escapeHtml(c.olt || '-') + (c.odp ? ' · ODP: ' + escapeHtml(c.odp) : ' · ODP: -') + '</div>' +
                    (c.ip_address ? '<div class="ftth-queue-item-ip">' + escapeHtml(c.ip_address) + '</div>' : '') +
                    '</div>' +
                    '<button type="button" class="ftth-queue-item-add" onclick="ftthHotspotAdd(' + row.i + ')"><i class="fa-solid fa-plus"></i> ADD</button>' +
                    '</div>';
            }).join('');
        }

        window.ftthHotspotAdd = function(idx) {
            var c = hotspotData[idx];
            if (!c) return;
            ftthOpenAddDevice({
                type: 'onu',
                name: c.name || '',
                ip: '',
                pppoe: '',
                parent: c.odp || c.olt || '',
                hotspot: true,
                notes: ['Hotspot ONU', c.serial_number ? 'SN: ' + c.serial_number : '', c.caller_id ? 'Caller: ' + c.caller_id : ''].filter(Boolean).join(' — ')
            });
        };

        var hotspotSearchEl = document.getElementById('ftthHotspotSearch');
        if (hotspotSearchEl) {
            hotspotSearchEl.addEventListener('input', function() {
                if (hotspotData.length) renderHotspot();
            });
        }

        /* ── Card Backup & Restore ── */

        var backupBusy = false;

        function setBackupStatus(msg, type, spin) {
            var s = document.getElementById('ftthBackupStatus');
            if (!s) return;
            s.className = 'ftth-mt-status ' + (type || 'info');
            s.innerHTML = (spin ? ftthLoaderTiny() : '') + escapeHtml(msg);
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
            ftthCloseAllCards();
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
                    var sh = document.getElementById('smtpHost');
                    var sp = document.getElementById('smtpPort');
                    var su = document.getElementById('smtpUsername');
                    var sw = document.getElementById('smtpPassword');
                    if (sh && r.data.smtp_host) sh.value = r.data.smtp_host;
                    if (sp && r.data.smtp_port) sp.value = r.data.smtp_port;
                    if (su && r.data.smtp_username) su.value = r.data.smtp_username;
                    if (sw && r.data.smtp_has_password) sw.placeholder = '•••••••• (tersimpan)';
                }
            }).catch(function() {});
        }

        window.ftthSaveBackup = function() {
            var email = document.getElementById('backupEmail').value.trim();
            var time = document.getElementById('backupTime').value.trim();
            var smtp = {
                smtp_host: (document.getElementById('smtpHost') || {}).value || '',
                smtp_port: (document.getElementById('smtpPort') || {}).value || '',
                smtp_username: (document.getElementById('smtpUsername') || {}).value || ''
            };
            var pwEl = document.getElementById('smtpPassword');
            /* Password hanya dikirim bila diisi — kosong berarti tetap pakai yang tersimpan */
            if (pwEl && pwEl.value.trim() !== '') smtp.smtp_password = pwEl.value.trim();
            setBackupBusy(true);
            setBackupStatus(ftthT('sync.menyimpan'), 'info', true);
            mtApi('/noc/features/map/backup/save', 'POST', Object.assign({ email: email, time: time }, smtp)).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || ftthT('toast.config_save_err'), 'error');
                    setBackupStatus(r.data.error || ftthT('sync.gagal_simpan'), 'fail');
                } else {
                    ftthToast(r.data.message || ftthT('toast.config_save_ok'), 'ok');
                    setBackupStatus(ftthT('sync.config_tersimpan'), 'ok');
                }
            }).catch(function() {
                ftthToast(ftthT('toast.config_save_err'), 'error');
                setBackupStatus(ftthT('sync.gagal_simpan'), 'fail');
            }).then(function() { setBackupBusy(false); });
        };

        window.ftthSendBackupNow = function() {
            var email = document.getElementById('backupEmail').value.trim();
            setBackupBusy(true);
            setBackupStatus(ftthT('sync.menyiapkan_backup'), 'info', true);
            mtApi('/noc/features/map/backup/send', 'POST', { email: email }).then(function(r) {
                if (r.status >= 400 || !r.data.ok) {
                    ftthToast(r.data.error || ftthT('toast.backup_fail'), 'error');
                    setBackupStatus(r.data.error || ftthT('sync.gagal_kirim'), 'fail');
                } else {
                    ftthToast(r.data.message || ftthT('toast.backup_sent'), 'ok');
                    setBackupStatus(ftthT('sync.terkirim').concat(r.data.filename || ''), 'ok');
                }
            }).catch(function() {
                ftthToast(ftthT('toast.backup_fail'), 'error');
                setBackupStatus(ftthT('sync.gagal_kirim'), 'fail');
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
                setBackupStatus(ftthT('sync.memulihkan'), 'info', true);
                mtUpload('/noc/features/map/backup/restore', f, { kind: restoreFileEl.dataset.kind || 'database' }).then(function(r) {
                    if (r.status >= 400 || !r.data.ok) {
                        ftthToast(r.data.error || ftthT('toast.restore_fail'), 'error');
                        setBackupStatus(r.data.error || ftthT('sync.gagal_restore'), 'fail');
                    } else {
                        ftthToast(r.data.message || ftthT('toast.restore_done'), 'ok');
                        setBackupStatus(ftthT('sync.restore_selesai'), 'ok');
                    }
                }).catch(function() {
                    ftthToast(ftthT('toast.restore_fail'), 'error');
                    setBackupStatus(ftthT('sync.gagal_restore'), 'fail');
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
                setBackupStatus(ftthT('sync.mengimpor_excel'), 'info', true);
                mtUpload('/noc/features/map/backup/excel-import', f).then(function(r) {
                    if (r.status >= 400 || !r.data.ok) {
                        ftthToast(r.data.error || ftthT('toast.import_fail'), 'error');
                        setBackupStatus(r.data.error || ftthT('sync.gagal_import'), 'fail');
                    } else {
                        ftthToast(r.data.message || ftthT('toast.import_done'), 'ok');
                        setBackupStatus(ftthT('sync.import_selesai'), 'ok');
                    }
                }).catch(function() {
                    ftthToast(ftthT('toast.import_fail'), 'error');
                    setBackupStatus(ftthT('sync.gagal_import'), 'fail');
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
                setBackupStatus(ftthT('sync.mengimpor_kmz'), 'info', true);
                mtUpload('/noc/features/map/backup/kmz-import', f).then(function(r) {
                    if (r.status >= 400 || !r.data.ok) {
                        ftthToast(r.data.error || ftthT('toast.import_fail'), 'error');
                        setBackupStatus(r.data.error || ftthT('sync.gagal_import'), 'fail');
                    } else {
                        ftthToast(r.data.message || ftthT('toast.import_done'), 'ok');
                        setBackupStatus(ftthT('sync.import_selesai'), 'ok');
                    }
                }).catch(function() {
                    ftthToast(ftthT('toast.import_fail'), 'error');
                    setBackupStatus(ftthT('sync.gagal_import'), 'fail');
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

        function ftthPonLabel(nomorPon) {
            var n = String(nomorPon || '').replace(/^pon\s*/i, '').trim();
            return n ? 'PON ' + n : '';
        }

        function ftthCoreColorName(val) {
            var v = String(val || '').trim().toLowerCase();
            if (!v) return '';
            for (var i = 0; i < ftthCoreColors.length; i++) {
                if (ftthCoreColors[i][1].toLowerCase() === v) return ftthCoreColors[i][0];
            }
            return String(val).trim();
        }

        function ftthCoreColorSelects() {
            var ponOpts = '';
            for (var p = 1; p <= 16; p++) ponOpts += '<option value="PON ' + p + '">PON ' + p + '</option>';
            var colOpts = ftthCoreColors.map(function(c) {
                return '<option value="' + c[1] + '" style="color:' + c[1] + '">' + c[0] + '</option>';
            }).join('');
            return '<div class="ftth-df"><label>Nomor PON</label><select id="ftthDevPonNo">' + ponOpts + '</select></div>' +
                '<div class="ftth-df"><label>Warna Core</label><select id="ftthDevCoreColor">' + colOpts + '</select></div>';
        }

        /* Varian untuk perangkat yang management core-nya hanya mengatur warna core (mis. HTB) */
        function ftthCoreColorOnly() {
            var colOpts = ftthCoreColors.map(function(c) {
                return '<option value="' + c[1] + '" style="color:' + c[1] + '">' + c[0] + '</option>';
            }).join('');
            return '<div class="ftth-df"><label>Warna Core</label><select id="ftthDevCoreColor">' + colOpts + '</select></div>';
        }

        var deviceTypeColors = {
            'ONU': '#8b5cf6', 'ODP': '#facc15', 'HTB': '#14b8a6', 'CLOSURE': '#eab308',
            'ODC': '#f97316', 'OTB': '#ec4899', 'OLT': '#ef4444', 'CUSTOM': '#94a3b8',
            'ROUTER': '#34d399', 'CUSTOMER': '#3b82f6'
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

            var isInfrastructure = (type === 'odc' || type === 'odp' || type === 'otb' || type === 'closure' || type === 'olt');
            var nameSpan = document.querySelector('[data-i18n="device.name"]');
            if (nameSpan) nameSpan.textContent = isInfrastructure ? ftthT('device.name_device') : ftthT('device.name');

            if (type === 'odc' || type === 'odp') {
                extra.innerHTML = '<div class="ftth-df"><label>Jumlah Kapasitas Port</label>' +
                    '<input type="text" id="ftthDevCapacity" placeholder="e.g. 288 / 16" autocomplete="off"></div>';
            } else if (type === 'onu') {
                extra.innerHTML = '<div class="ftth-df"><label>IP Address</label>' +
                    '<input type="text" id="ftthDevIp" placeholder="e.g. 192.168.1.5" autocomplete="off"></div>' +
                    '<div class="ftth-df"><label>' + ftthT('device.user_pppoe') + ' <button type="button" class="ftth-pppoe-edit" id="ftthPppoeToggle" title="' + ftthT('detail.edit_btn') + ' User PPPoE" onclick="ftthTogglePppoeField()"><i class="fa-solid fa-pen"></i></button></label>' +
                    '<input type="text" id="ftthDevPppoe" placeholder="e.g. alk-001" autocomplete="off" hidden></div>';
            } else if (type === 'htb') {
                extra.innerHTML = '<div class="ftth-df"><label>User PPPoE</label>' +
                    '<input type="text" id="ftthDevPppoe" placeholder="e.g. alk-001" autocomplete="off"></div>';
            } else {
                extra.innerHTML = '';
            }

            var canCore = (type === 'olt' || type === 'odc' || type === 'odp' || type === 'htb');
            coreChk.hidden = !canCore;
            if (!canCore) coreMgmt.checked = false;

            if (canCore && coreOn) {
                if (type === 'olt') {
                    coreFields.innerHTML = '<div class="ftth-df"><label>Jumlah PON</label>' +
                        '<input type="text" id="ftthDevPonCount" placeholder="e.g. 8" autocomplete="off"></div>';
                } else if (type === 'htb') {
                    /* HTB: management core hanya untuk atur warna core */
                    coreFields.innerHTML = ftthCoreColorOnly();
                } else {
                    coreFields.innerHTML = ftthCoreColorSelects();
                }
                coreFields.hidden = false;
            } else {
                coreFields.hidden = true;
                coreFields.innerHTML = '';
            }
        }

        function ftthTogglePppoeField() {
            var pp = document.getElementById('ftthDevPppoe');
            var btn = document.getElementById('ftthPppoeToggle');
            if (!pp || !btn) return;
            var show = pp.hidden;
            pp.hidden = !show;
            btn.classList.toggle('active', show);
            if (show) pp.focus();
        }

        var ftthParentsLoaded = false;
        var ftthEditDeviceId = null;
        var ftthAddHotspot = false;

        function loadDeviceParents(cb) {
            var sel = document.getElementById('ftthDevParent');
            if (ftthParentsLoaded) {
                if (cb) cb();
                return;
            }
            mtApi('/noc/features/map/device/parents', 'GET').then(function(r) {
                if (r.data && r.data.ok && r.data.parents) {
                    sel.innerHTML = '<option value="">None</option>' + r.data.parents.map(function(p) {
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
            ftthCloseAllCards();
            prefill = prefill || {};
            ftthCloseDetail();
            ftthParentsLoaded = false;
            ftthEditDeviceId = prefill.id || null;
            var isEdit = !!ftthEditDeviceId;
            document.getElementById('ftthDeviceType').value = prefill.type || '';
            document.getElementById('ftthDevName').value = prefill.name || '';
            document.getElementById('ftthDevLat').value = '';
            document.getElementById('ftthDevLng').value = '';
            document.getElementById('ftthDevLocation').value = '';
            document.getElementById('ftthDevCoreMgmt').checked = !!prefill.management_core;
            document.getElementById('ftthAddDeviceTitle').innerHTML =
                '<i class="fa-solid ' + (isEdit ? 'fa-pen' : 'fa-plus') + '"></i> ' +
                (isEdit ? 'Edit Perangkat' : (prefill.type === 'onu' ? 'Tambah Perangkat — ONU' : 'Tambah Perangkat'));
            ftthRenderDeviceFields();

            ftthAddHotspot = !!prefill.hotspot;

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
                        html: '<div class="ftth-tag-wrap"><span class="ftth-tag-note">Silahkan geser pin</span><i class="fa-solid fa-hand-point-down ftth-tag-hand"></i><i class="fa-solid fa-location-dot ftth-tag-dot"></i></div>',
                        iconSize: [150, 72],
                        iconAnchor: [75, 70]
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
                setDeviceStatus(ftthT('device.nama_wajib'), '#f87171');
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
            } else if (type === 'onu' || type === 'htb') {
                var ipEl = document.getElementById('ftthDevIp');
                ip = (ipEl && ipEl.value.trim()) ? ipEl.value.trim() : null;
                var ppEl = document.getElementById('ftthDevPppoe');
                if (ppEl && ppEl.value.trim()) attributes.pppoe_user = ppEl.value.trim();
            }

            if (ftthAddHotspot) attributes.hotspot = true;

            if (type === 'olt' && document.getElementById('ftthDevCoreMgmt').checked) {
                var ponCount = document.getElementById('ftthDevPonCount');
                if (ponCount && ponCount.value.trim()) attributes.jumlah_pon = ponCount.value.trim();
            }

            if ((type === 'odc' || type === 'odp') && document.getElementById('ftthDevCoreMgmt').checked) {
                var ponNo = document.getElementById('ftthDevPonNo');
                var coreCol = document.getElementById('ftthDevCoreColor');
                if (ponNo && ponNo.value) attributes.nomor_pon = ponNo.value;
                if (coreCol && coreCol.value) attributes.warna_core = coreCol.value;
            } else if (type === 'htb' && document.getElementById('ftthDevCoreMgmt').checked) {
                /* HTB: hanya simpan warna core — kabel di peta otomatis mengikuti warna ini */
                var htbCol = document.getElementById('ftthDevCoreColor');
                if (htbCol && htbCol.value) attributes.warna_core = htbCol.value;
            }

            var payload = {
                id: ftthEditDeviceId,
                type: type,
                name: name,
                capacity: capacity,
                ip_address: ip,
                notes: '',
                latitude: document.getElementById('ftthDevLat').value.trim(),
                longitude: document.getElementById('ftthDevLng').value.trim(),
                location: document.getElementById('ftthDevLocation').value.trim(),
                attributes: attributes
            };

            setDeviceStatus('Menyimpan...', '#60a5fa');
            mtApi('/noc/features/map/device/save', 'POST', payload).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthToast(r.data.message || ftthT('toast.device_saved'), 'ok');
                    setDeviceStatus('Tersimpan', '#4ade80');
                    ftthEditDeviceId = null;
                    ftthParentsLoaded = false;
                    /* Re-render detail card if open for this device after markers refresh */
                    var savedId = payload.id;
                    loadMapMarkers();
                    var retryCount = 0;
                    var checkRefresh = function() {
                        var updated = markersCache.find(function(cm) { return cm.id === savedId; });
                        if (updated && ftthDetailData && ftthDetailData.id === savedId) {
                            ftthShowDetail(updated);
                        } else if (retryCount++ < 10) {
                            setTimeout(checkRefresh, 200);
                        }
                    };
                    setTimeout(checkRefresh, 500);
                    setTimeout(ftthCloseAddDevice, 900);
                } else {
                    setDeviceStatus((r.data && r.data.error) || 'Gagal simpan', '#f87171');
                }
            }).catch(function() {
                setDeviceStatus('Gagal menyimpan', '#f87171');
            });
        }

        function ftthOpenDevices() {
            ftthCloseAllCards();
            var devCard = document.getElementById('ftthDevicesCard');
            devCard.style.left = '';
            devCard.style.top = '';
            devCard.style.transform = '';
            ftthBrowseType = null;
            document.getElementById('ftthBrowseSearch').value = '';
            document.getElementById('ftthDevicesBackdrop').hidden = false;
            document.getElementById('ftthDevicesCats').hidden = false;
            document.getElementById('ftthDevicesBrowse').hidden = true;
            if (ftthDevicesData.length) {
                ftthRenderCategories();
            } else {
                document.getElementById('ftthDevicesCats').innerHTML = ftthLoaderHtml(ftthT('loader.devices'));
            }
            loadDevices();
        }

        function ftthCloseDevices() {
            document.getElementById('ftthDevicesBackdrop').hidden = true;
        }

        function loadDevices() {
            mtApi('/noc/features/map/device', 'GET').then(function(r) {
                if (r.data && r.data.ok) {
                    ftthDevicesData = r.data.devices || [];
                    ftthRoutersData = r.data.routers || [];
                    ftthOltsData = r.data.olts || [];
                    ftthCustomersData = r.data.customers || [];
                    ftthCounts = r.data.counts || {};
                    if (ftthBrowseType) {
                        ftthBrowseData = ftthBuildBrowseData(ftthBrowseType);
                        ftthBrowseRender();
                    } else {
                        ftthRenderCategories();
                    }
                } else if (!ftthDevicesData.length) {
                    document.getElementById('ftthDevicesCats').innerHTML = '<div class="ftth-device-empty">' + ftthT('msg.no_data') + '</div>';
                }
            }).catch(function() {
                if (!ftthDevicesData.length) {
                    document.getElementById('ftthDevicesCats').innerHTML = '<div class="ftth-device-empty">' + ftthT('msg.gagal_load') + '</div>';
                }
            });
        }

        var ftthDevicesData = [];
        var ftthRoutersData = [];
        var ftthOltsData = [];
        var ftthCustomersData = [];
        var ftthCounts = {};
        var ftthDevCatDefs = [
            { type: 'router', label: ftthT('cat.router_induk'), icon: 'fa-tower-cell', color: '#34d399' },
            { type: 'olt', label: 'OLT', icon: 'fa-server', color: '#ef4444' },
            { type: 'otb', label: 'OTB', icon: 'fa-box-archive', color: '#ec4899' },
            { type: 'odc', label: 'ODC', icon: 'fa-boxes-stacked', color: '#f97316' },
            { type: 'odp', label: 'ODP', icon: 'fa-code-branch', color: '#facc15' },
            { type: 'htb', label: 'HTB', icon: 'fa-network-wired', color: '#14b8a6' },
            { type: 'onu', label: 'ONU (PPPoE)', icon: 'fa-wifi', color: '#8b5cf6' },
            { type: 'onu_hotspot', label: 'ONU (Hotspot)', icon: 'fa-tower-cell', color: '#fb923c' }
        ];

        function ftthRenderCategories() {
            var box = document.getElementById('ftthDevicesCats');
            if (!box) return;
            var total = 0;
            ftthDevCatDefs.forEach(function(def) { total += (ftthCounts[def.type] || 0); });
            document.getElementById('ftthDevicesHead').innerHTML =
                '<span class="ftth-modal-title"><i class="fa-solid fa-hdd"></i> ' + ftthT('device.daftar_perangkat') + '</span>' +
                '<span class="ftth-device-status" id="ftthDevicesCount">' + total + ' ' + ftthT('device.perangkat') + '</span>' +
                '<button type="button" class="ftth-modal-close" onclick="ftthCloseDevices()"><i class="fa-solid fa-xmark"></i></button>';
            box.innerHTML = ftthDevCatDefs.map(function(def) {
                var n = ftthCounts[def.type] || 0;
                return '<div class="ftth-dev-cat" style="--c:' + def.color + '" onclick="ftthBrowseCategory(\'' + def.type + '\')">' +
                    '<div class="ftth-dev-cat-ic"><i class="fa-solid ' + def.icon + '"></i></div>' +
                    '<div class="ftth-dev-cat-body">' +
                    '<div class="ftth-dev-cat-name">' + def.label + '</div>' +
                    '<div class="ftth-dev-cat-jelajahi">' + ftthT('device.jelajahi') + '</div>' +
                    '</div>' +
                    '<span class="ftth-dev-cat-badge">' + n + '</span>' +
                    '</div>';
            }).join('');
        }

        function ftthDevicesShowOverview() {
            ftthBrowseType = null;
            document.getElementById('ftthDevicesBrowse').hidden = true;
            document.getElementById('ftthDevicesCats').hidden = false;
            ftthRenderCategories();
        }

        var ftthBrowseType = null;
        var ftthBrowseData = [];

        function ftthBrowseCategory(type) {
            var def = ftthDevCatDefs.find(function(d) { return d.type === type; });
            if (!def) return;
            ftthBrowseType = type;
            document.getElementById('ftthBrowseSearch').value = '';
            document.getElementById('ftthDevicesCats').hidden = true;
            document.getElementById('ftthDevicesBrowse').hidden = false;
            document.getElementById('ftthDevicesHead').innerHTML =
                '<span class="ftth-modal-title"><i class="fa-solid fa-hdd"></i> ' + ftthT('device.daftar') + ' ' + def.label + '</span>' +
                '<button type="button" class="ftth-browse-back" onclick="ftthDevicesShowOverview()"><i class="fa-solid fa-arrow-left"></i> ' + ftthT('device.kembali') + '</button>';
            ftthBrowseData = ftthBuildBrowseData(type);
            ftthBrowseRender();
        }

        function ftthBuildBrowseData(type) {
            if (type === 'onu') return ftthCustomersData.filter(function(d) { return (d.customer_type || '') !== 'hotspot'; });
            if (type === 'onu_hotspot') return ftthCustomersData.filter(function(d) { return (d.customer_type || '') === 'hotspot'; });
            if (type === 'router') return ftthRoutersData.slice();
            if (type === 'olt') return ftthOltsData.slice();
            return ftthDevicesData.filter(function(d) { return String(d.type).toLowerCase() === type; });
        }

        function ftthBrowseFilter() {
            ftthBrowseRender();
        }

        function ftthBrowseRender() {
            var q = document.getElementById('ftthBrowseSearch').value.toLowerCase().trim();
            var list = ftthBrowseData.filter(function(d) {
                if (!q) return true;
                var hay = (d.name + ' ' + (d.customer_code || '') + ' ' + (d.pppoe_username || '') + ' ' + (d.ip || d.ip_address || '') + ' ' + (d.model || '') + ' ' + (d.location || '')).toLowerCase();
                return hay.indexOf(q) !== -1;
            });
            document.getElementById('ftthBrowseDelCount').textContent = list.length;
            var box = document.getElementById('ftthBrowseList');
            if (!list.length) {
                box.innerHTML = '<div class="ftth-device-empty">' + (q ? ftthT('device.tidak_ditemukan') : ftthT('device.belum_ada_data')) + '</div>';
                return;
            }
            var isOnu = ftthBrowseType === 'onu' || ftthBrowseType === 'onu_hotspot';
            var isNet = ftthBrowseType === 'router' || ftthBrowseType === 'olt';
            var color = ftthDeviceColor(isOnu ? 'onu' : ftthBrowseType);
            box.innerHTML = list.map(function(d) {
                var status = isOnu ? d.status : (isNet ? (d.status || d.connection_status) : d.status);
                var stClass = (status === 'active' || status === 'online') ? 'st-online' : ((status === 'suspended' || status === 'offline') ? 'st-offline' : '');
                var stLabel = status ? String(status).toUpperCase() : 'SET';
                var sub, name, mainOn, editOn, delOn;
                if (isOnu && d.kind === 'device') {
                    name = d.name;
                    sub = d.pppoe_username || '';
                    mainOn = 'ftthBrowseFlyDevice(' + d.id + ')';
                    editOn = 'ftthBrowseEditDevice(' + d.id + ')';
                    delOn = 'ftthBrowseDeleteDevice(' + d.id + ')';
                } else if (isOnu) {
                    name = d.customer_code + ' — ' + d.name;
                    sub = d.pppoe_username || '';
                    mainOn = 'ftthBrowseFlyCustomer(' + d.id + ')';
                    editOn = 'ftthBrowseEditCustomer(' + d.id + ')';
                    delOn = 'ftthBrowseDeleteCustomer(' + d.id + ')';
                } else if (isNet) {
                    name = d.name;
                    var netIp = d.ip || d.ip_address || '';
                    var ver = d.routeros_version ? ' — v' + d.routeros_version : '';
                    if (ftthBrowseType === 'router') {
                        sub = netIp + ver;
                        mainOn = 'ftthOpenMikrotik()';
                    } else {
                        /* OLT: tampilkan brand/type (mis. C-DATA FD1601S) — nama OLT
                           adalah entitasnya, brand+model adalah tipenya */
                        var BRAND_LABEL = { cdata: 'C-DATA', huawei: 'Huawei', zte: 'ZTE', fiberhome: 'FiberHome', vsol: 'VSOL', hioso: 'Hioso', hsgq: 'HSGQ' };
                        var bl = BRAND_LABEL[String(d.brand || '').toLowerCase()] || d.brand || '';
                        sub = [bl, d.model].filter(Boolean).join(' ') || netIp;
                        mainOn = 'ftthBrowseFlyOlt(' + d.id + ')';
                    }
                    editOn = ftthBrowseType === 'router' ? 'ftthOpenMikrotik()' : 'ftthOpenOlt()';
                    delOn = ftthBrowseType === 'router' ? 'ftthDelRouter(' + d.id + ')' : 'ftthDelOlt(' + d.id + ')';
                } else {
                    name = d.name;
                    sub = d.location || '';
                    mainOn = 'ftthBrowseFlyDevice(' + d.id + ')';
                    editOn = 'ftthBrowseEditDevice(' + d.id + ')';
                    delOn = 'ftthBrowseDeleteDevice(' + d.id + ')';
                }
                return '<div class="ftth-device-row" style="--fc:' + color + '">' +
                    '<span class="ftth-device-row-main" title="' + (isNet ? ftthT('detail.open_settings') : ftthT('detail.goto_peta')) + '" onclick="' + mainOn + '">' +
                    '<span class="ftth-device-row-name">' + escapeHtml(name) + '</span>' +
                    '<span class="ftth-device-row-sub">' + escapeHtml(sub) + '</span>' +
                    '</span>' +
                    '<span class="ftth-device-row-status ' + stClass + '">' + stLabel + '</span>' +
                    '<button type="button" class="ftth-device-row-edit" title="' + ftthT('detail.edit_btn') + '" onclick="' + editOn + '"><i class="fa-solid fa-pen"></i></button>' +
                    '<button type="button" class="ftth-device-row-del" title="' + ftthT('detail.hapus_btn') + '" onclick="' + delOn + '"><i class="fa-solid fa-trash-can"></i></button>' +
                    '</div>';
            }).join('');
        }

        function ftthBrowseFly(lat, lng, label) {
            if (lat == null || lng == null) {
                ftthToast(ftthT('toast.no_coords_data'), 'warn');
                return;
            }
            ftthCloseDevices();
            map.flyTo([Number(lat), Number(lng)], Math.max(map.getZoom(), 16), { duration: 1.2 });
            ftthToast(ftthT('toast.goto') + ' ' + label, 'ok');
        }

        function ftthBrowseFlyDevice(id) {
            var d = ftthDevicesData.find(function(x) { return x.id === id; });
            if (!d) return;
            ftthBrowseFly(d.latitude, d.longitude, d.name);
        }

        function ftthBrowseFlyCustomer(id) {
            var c = ftthCustomersData.find(function(x) { return x.id === id; });
            if (!c) return;
            ftthBrowseFly(c.lat, c.lon, c.customer_code + ' - ' + c.name);
        }

        /* Klik OLT di card Perangkat -> terbang ke marker OLT di peta + buka detailnya.
           Nama di card (olts.name, mis. "OLT UTAMA") dan nama device OLT di map
           ("C-DATA FD1601S") adalah entitas yang sama: dicocokkan lewat nama
           dinormalisasi ATAU brand+model. */
        window.ftthBrowseFlyOlt = function(id) {
            var o = ftthOltsData.find(function(x) { return x.id === id; });
            if (!o) return;
            var norm = function(s) { return String(s || '').toUpperCase().replace(/[^A-Z0-9]/g, ''); };
            var oName = norm(o.name);
            var oType = norm((o.brand || '') + (o.model || ''));
            var m = window.ftthMapApi ? window.ftthMapApi.markersCache().find(function(x) {
                if (String(x.type || '').toUpperCase() !== 'OLT') return false;
                var mName = norm(x.label);
                var ma = (x.attributes && typeof x.attributes === 'object') ? x.attributes : {};
                var mType = norm((ma.merk || ma.brand || '') + (ma.model || ''));
                return (oName && mName.indexOf(oName) !== -1) ||
                       (oName && oName.indexOf(mName) !== -1) ||
                       (oType && mType && (mType.indexOf(oType) !== -1 || oType.indexOf(mType) !== -1));
            }) : null;
            /* Fallback: kalau tidak ada yang cocok, pakai satu-satunya marker OLT */
            if (!m) {
                var olts = window.ftthMapApi ? window.ftthMapApi.markersCache().filter(function(x) { return String(x.type || '').toUpperCase() === 'OLT'; }) : [];
                m = olts.length === 1 ? olts[0] : null;
            }
            if (!m || m.lat == null) { ftthToast(ftthT('toast.no_coords_data'), 'warn'); return; }
            ftthCloseDevices();
            map.flyTo([Number(m.lat), Number(m.lon)], Math.max(map.getZoom(), 17), { duration: 1.2 });
            setTimeout(function() { window.ftthFocusMarker(m.id); }, 1300);
            ftthToast(ftthT('toast.goto') + ' ' + o.name + (m.label && m.label !== o.name ? ' (' + m.label + ')' : ''), 'ok');
        };

        function ftthBrowseEditDevice(id) {
            var idx = ftthDevicesData.findIndex(function(x) { return x.id === id; });
            if (idx === -1) return;
            ftthEditDeviceFromList(idx);
        }

        function ftthBrowseDeleteDevice(id) {
            ftthDeleteDevice(id);
        }

        function ftthBrowseEditCustomer(id) {
            var c = ftthCustomersData.find(function(x) { return x.id === id; });
            if (!c) return;
            window.open('/customer/' + c.customer_code + '/edit', '_self');
        }

        function ftthBrowseDeleteCustomer(id) {
            var c = ftthCustomersData.find(function(x) { return x.id === id; });
            if (!c) return;
            if (!confirm(ftthT('confirm.hapus_pelanggan') + ' "' + c.name + '"?')) return;
            mtApi('/noc/features/map/customer/delete', 'POST', { id: id }).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthToast(r.data.message || 'Pelanggan dihapus', 'ok');
                    loadDevices();
                    loadMapMarkers();
                } else {
                    ftthToast((r.data && r.data.error) || 'Gagal menghapus pelanggan', 'error');
                }
            }).catch(function() {
                ftthToast(ftthT('toast.customer_delete_fail'), 'error');
            });
        }

        function ftthBrowseDeleteAll() {
            var n = Number(document.getElementById('ftthBrowseDelCount').textContent);
            if (!n) {
                ftthToast(ftthT('toast.no_data_to_delete'), 'warn');
                return;
            }
            var def = ftthDevCatDefs.find(function(d) { return d.type === ftthBrowseType; });
            if (!confirm(ftthT('confirm.hapus_semua') + ' ' + n + ' ' + ftthT('confirm.data_in') + ' ' + (def ? def.label : '') + '?')) return;
            var url = ftthBrowseType === 'onu' ? '/noc/features/map/customer/delete-all' : '/noc/features/map/device/delete-all';
            var body = ftthBrowseType === 'onu' ? null : { type: ftthBrowseType };
            mtApi(url, 'POST', body).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthToast(r.data.message || 'Data dihapus', 'ok');
                    loadDevices();
                    loadMapMarkers();
                } else {
                    ftthToast((r.data && r.data.error) || 'Gagal menghapus data', 'error');
                }
            }).catch(function() {
                ftthToast(ftthT('toast.data_delete_fail'), 'error');
            });
        }

        function ftthDeviceToMarker(d) {
            var attrs = d.attributes || {};
            return {
                id: d.id,
                source: 'device',
                type: d.type_label || String(d.type).toUpperCase(),
                label: d.name,
                lat: d.latitude != null ? Number(d.latitude) : null,
                lon: d.longitude != null ? Number(d.longitude) : null,
                location: d.location || '',
                status: d.status || null,
                detail: [d.brand, d.model].filter(Boolean).join(' · '),
                parent: attrs.induk || null,
                brand: d.brand,
                model: d.model,
                capacity: d.capacity,
                ip_address: d.ip_address,
                attributes: attrs,
                notes: d.notes || '',
                customer_id: d.customer_id,
                onu_type: d.onu_type
            };
        }

        function ftthDeviceDetailFromList(idx) {
            var d = ftthDevicesData[idx];
            if (!d) return;
            ftthShowDetail(ftthDeviceToMarker(d));
        }

        function ftthEditDeviceFromList(idx) {
            var d = ftthDevicesData[idx];
            if (!d) return;
            var attrs = d.attributes || {};
            ftthOpenAddDevice({
                id: d.id,
                type: String(d.type).toLowerCase(),
                name: d.name,
                notes: d.notes || '',
                parent: attrs.induk || '',
                capacity: d.capacity,
                ip: d.ip_address,
                pppoe: attrs.pppoe_user,
                management_core: Number(attrs.management_core) === 1,
                jumlah_pon: attrs.jumlah_pon,
                nomor_pon: attrs.nomor_pon,
                warna_core: attrs.warna_core,
                lat: d.latitude != null ? Number(d.latitude) : null,
                lng: d.longitude != null ? Number(d.longitude) : null,
                location: d.location || ''
            });
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
                ftthToast(ftthT('toast.status_change_fail'), 'error');
            });
        }

        function ftthDeleteDevice(id) {
            if (!confirm(ftthT('confirm.hapus_perangkat'))) return;
            mtApi('/noc/features/map/device/delete', 'POST', { id: id }).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthToast(r.data.message || 'Perangkat dihapus', 'ok');
                    ftthParentsLoaded = false;
                    loadDevices();
                    loadMapMarkers();
                }
            }).catch(function() {
                ftthToast(ftthT('toast.device_delete_fail'), 'error');
            });
        }

        var ftthOnuData = [];
        var ftthOnuPage = 1;
        var ftthOnuPageSize = 15;

        function ftthOpenOnuTable() {
            ftthCloseAllCards();
            var onuCard = document.getElementById('ftthOnuTableCard');
            onuCard.style.left = '';
            onuCard.style.top = '';
            onuCard.style.transform = '';
            document.getElementById('ftthOnuTableBackdrop').hidden = false;
            document.getElementById('ftthOnuTableSearch').value = '';
            ftthOnuPage = 1;
            ftthOnuData = [];
            document.getElementById('ftthOnuTableBody').innerHTML = '<tr><td colspan="9" class="ftth-device-empty ftth-onu-loading">' + ftthLoaderHtml(ftthT('loader.onu')) + '</td></tr>';
            document.getElementById('ftthOnuTableInfo').textContent = '';
            document.getElementById('ftthOnuPageNum').textContent = '1/1';
            loadOnuTable();
        }

        function ftthCloseOnuTable() {
            document.getElementById('ftthOnuTableBackdrop').hidden = true;
        }

        function loadOnuTable() {
            mtApi('/noc/features/map/onu-table', 'GET').then(function(r) {
                if (r.data && r.data.ok) {
                    ftthOnuData = r.data.rows || [];
                    ftthOnuPage = 1;
                    renderOnuTable();
                }
            }).catch(function() {
                document.getElementById('ftthOnuTableBody').innerHTML = '<tr><td colspan="9" class="ftth-device-empty">' + ftthT('msg.gagal_load') + '</td></tr>';
            });
        }

        function ftthOnuTableFilter() {
            ftthOnuPage = 1;
            renderOnuTable();
        }

        function ftthOnuPageGo(dir) {
            var query = document.getElementById('ftthOnuTableSearch').value.toLowerCase().trim();
            var filtered = ftthOnuData.filter(function(d) {
                if (!query) return true;
                return [d.nama, d.type_onu, d.pppoe_username, d.ip_address, d.odp, d.olt].join(' ').toLowerCase().indexOf(query) !== -1;
            });
            var pages = Math.max(1, Math.ceil(filtered.length / ftthOnuPageSize));
            var next = dir === 'prev' ? ftthOnuPage - 1 : ftthOnuPage + 1;
            ftthOnuPage = Math.min(pages, Math.max(1, next));
            renderOnuTable();
        }

        function renderOnuTable() {
            var body = document.getElementById('ftthOnuTableBody');
            var info = document.getElementById('ftthOnuTableInfo');
            var pageNum = document.getElementById('ftthOnuPageNum');
            var prevBtn = document.getElementById('ftthOnuPagePrev');
            var nextBtn = document.getElementById('ftthOnuPageNext');

            var query = document.getElementById('ftthOnuTableSearch').value.toLowerCase().trim();
            var filtered = ftthOnuData.filter(function(d) {
                if (!query) return true;
                return [d.nama, d.type_onu, d.pppoe_username, d.ip_address, d.odp, d.olt].join(' ').toLowerCase().indexOf(query) !== -1;
            });

            var pages = Math.max(1, Math.ceil(filtered.length / ftthOnuPageSize));
            if (ftthOnuPage > pages) ftthOnuPage = pages;
            var start = (ftthOnuPage - 1) * ftthOnuPageSize;
            var slice = filtered.slice(start, start + ftthOnuPageSize);

            info.textContent = ftthT('onu.info') + ' ' + (filtered.length ? start + 1 : 0) + '-' + (start + slice.length) + ' ' + ftthT('onu.info_of') + ' ' + filtered.length + ' ' + ftthT('onu.info_data');
            pageNum.textContent = ftthOnuPage + '/' + pages;
            prevBtn.disabled = ftthOnuPage <= 1;
            nextBtn.disabled = ftthOnuPage >= pages;

            if (!slice.length) {
                body.innerHTML = '<tr><td colspan="9" class="ftth-device-empty">' + ftthT('loader.onu_empty') + '</td></tr>';
                return;
            }
            body.innerHTML = slice.map(function(d, i) {
                var typeBadge = d.type_onu === 'Hotspot'
                    ? '<span style="display:inline-block;padding:1px 7px;border-radius:4px;font-size:11px;font-weight:600;background:#fb923c;color:#fff">Hotspot</span>'
                    : '<span style="display:inline-block;padding:1px 7px;border-radius:4px;font-size:11px;font-weight:600;background:#22d3ee;color:#fff">PPPoE</span>';
                return '<tr>' +
                    '<td>' + (start + i + 1) + '</td>' +
                    '<td><b>' + escapeHtml(d.nama) + '</b></td>' +
                    '<td>' + typeBadge + '</td>' +
                    '<td>' + escapeHtml(d.pppoe_username || '-') + '</td>' +
                    '<td>' + escapeHtml(d.ip_address || '-') + '</td>' +
                    '<td>' + escapeHtml(d.koordinat || '-') + '</td>' +
                    '<td>' + escapeHtml(d.htb || '-') + '</td>' +
                    '<td>' + escapeHtml(d.odp || '-') + '</td>' +
                    '<td>' + escapeHtml(d.olt || '-') + '</td>' +
                    '</tr>';
            }).join('');
        }

        /* ── Toggle dropdown Print/Export (klik, bukan hover) ── */
        function ftthCloseOnuDd() {
            document.querySelectorAll('.ftth-onu-dd.open').forEach(function(d) { d.classList.remove('open'); });
        }
        document.querySelectorAll('.ftth-onu-dd > .ftth-onu-table-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var dd = btn.parentElement;
                var wasOpen = dd.classList.contains('open');
                ftthCloseOnuDd();
                if (!wasOpen) dd.classList.add('open');
            });
        });
        document.querySelectorAll('.ftth-onu-dd-item').forEach(function(item) {
            item.addEventListener('click', function() { ftthCloseOnuDd(); });
        });
        document.addEventListener('click', ftthCloseOnuDd);

        function ftthOnuTablePrint(type) {
            var q = document.getElementById('ftthOnuTableSearch').value.trim();
            var params = [];
            if (q) params.push('q=' + encodeURIComponent(q));
            if (type) params.push('type=' + encodeURIComponent(type));
            window.open('/noc/features/map/onu-table/print' + (params.length ? '?' + params.join('&') : ''), '_blank');
        }

        function ftthOnuTableExport(type) {
            var q = document.getElementById('ftthOnuTableSearch').value.trim();
            var params = [];
            if (q) params.push('q=' + encodeURIComponent(q));
            if (type) params.push('type=' + encodeURIComponent(type));
            fetch('/noc/features/map/onu-table/export' + (params.length ? '?' + params.join('&') : ''), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/csv' }
            }).then(function(r) { return r.blob(); }).then(function(blob) {
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'tabel-onu' + (type ? '-' + type : '') + '.csv';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            }).catch(function() {
                ftthToast(ftthT('toast.export_fail'), 'error');
            });
        }

        /* ── Card info perangkat (klik marker) ── */

        var ftthDetailData = null;
        var ftthCardGeoSeq = 0;
        var ftthActiveMarker = null;
        var ftthActiveIconEl = null;
        var ftthCardDocked = false;
        var ftthCardDragging = false;

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
                        document.getElementById('ftthDetailLoc').innerHTML = '<i class="fa-solid fa-location-dot"></i><span>' + ftthT('detail.tidak_diketahui') + '</span>';
                    }
                    ftthPositionDetailCard();
                })
                .catch(function() {
                    if (seq !== ftthCardGeoSeq) return;
                    document.getElementById('ftthDetailLoc').innerHTML = '<i class="fa-solid fa-location-dot"></i><span>' + ftthT('detail.tidak_diketahui') + '</span>';
                    ftthPositionDetailCard();
                });
        }

        function ftthPositionDetailCard() {
            var card = document.getElementById('ftthDetailCard');
            if (!card || card.hidden || !ftthActiveMarker) return;
            if (!ftthCardDocked) return;
            function doPos() {
                var pad = 8;
                var ch = card.offsetHeight || 240;
                /* Selurus mungkin ke atas: tepat di bawah toolbar (tinggi toolbar diukur dinamis) */
                var tb = document.querySelector('.ftth-toolbar');
                var topPad = tb ? Math.round(tb.getBoundingClientRect().bottom) + 8 : 56;
                var maxTop = Math.max(pad, window.innerHeight - ch - pad);
                var top = Math.max(pad, Math.min(topPad, maxTop));
                card.style.left = pad + 'px';
                card.style.top = top + 'px';
                card.style.transform = 'none';
                card.style.visibility = 'visible';
            }
            card.style.visibility = 'hidden';
            doPos();
            requestAnimationFrame(function() {
                doPos();
                requestAnimationFrame(doPos);
            });
            ftthCardDocked = false;
        }

        function ftthInitDetailDrag() {
            var card = document.getElementById('ftthDetailCard');
            var dg = false, ox = 0, oy = 0, L = 0, T = 0;

            function ptOf(e) {
                return e.touches ? e.touches[0] : e;
            }
            function findHandle(target) {
                return target.closest('.ftth-detail-head') || target.closest('.ftth-odc-head');
            }
            function start(e) {
                if (e.target.closest('.ftth-modal-close, .ftth-odc-close')) return;
                var handle = findHandle(e.target);
                if (!handle) return;
                dg = true;
                ftthCardDragging = true;
                var pt = ptOf(e);
                ox = pt.clientX;
                oy = pt.clientY;
                var r = card.getBoundingClientRect();
                L = r.left;
                T = r.top;
                card.style.left = L + 'px';
                card.style.top = T + 'px';
                card.style.transform = 'none';
                ftthCardDocked = false;
                handle.style.cursor = 'grabbing';
                if (e.cancelable) e.preventDefault();
            }
            function move(e) {
                if (!dg) return;
                var pt = ptOf(e);
                card.style.left = (L + pt.clientX - ox) + 'px';
                card.style.top = (T + pt.clientY - oy) + 'px';
                if (e.cancelable) e.preventDefault();
            }
            function stop() {
                if (!dg) return;
                dg = false;
                ftthCardDragging = false;
                var handles = card.querySelectorAll('.ftth-detail-head, .ftth-odc-head');
                handles.forEach(function(h) { h.style.cursor = 'grab'; });
            }
            card.addEventListener('mousedown', start);
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', stop);
            card.addEventListener('touchstart', start, { passive: false });
            document.addEventListener('touchmove', move, { passive: false });
            document.addEventListener('touchend', stop);
            document.addEventListener('touchcancel', stop);
        }
        ftthInitDetailDrag();

        (function() {
            var card = document.getElementById('ftthCablePropsCard');
            if (!card) return;
            var head = card.querySelector('.ftth-cable-props-head');
            if (head) head.style.cursor = 'grab';
            var dg = false, ox = 0, oy = 0, cl = 0, ct = 0;
            function ptOf(e) { return e.touches ? e.touches[0] : e; }
            function start(e) {
                if (!e.target.closest('.ftth-cable-props-head')) return;
                if (e.target.closest('button')) return;
                dg = true;
                var pt = ptOf(e);
                ox = pt.clientX;
                oy = pt.clientY;
                var r = card.getBoundingClientRect();
                cl = r.left;
                ct = r.top;
                card.style.left = cl + 'px';
                card.style.top = ct + 'px';
                if (head) head.style.cursor = 'grabbing';
                if (e.cancelable) e.preventDefault();
            }
            function move(e) {
                if (!dg) return;
                var pt = ptOf(e);
                card.style.left = (cl + pt.clientX - ox) + 'px';
                card.style.top = (ct + pt.clientY - oy) + 'px';
                if (e.cancelable) e.preventDefault();
            }
            function stop() {
                if (!dg) return;
                dg = false;
                if (head) head.style.cursor = 'grab';
            }
            card.addEventListener('mousedown', start);
            document.addEventListener('mousemove', move);
            document.addEventListener('mouseup', stop);
            card.addEventListener('touchstart', start, { passive: false });
            document.addEventListener('touchmove', move, { passive: false });
            document.addEventListener('touchend', stop);
            document.addEventListener('touchcancel', stop);
        })();

        /* Teks koordinat seragam untuk semua kartu detail (sesuai posisi perangkat) */
        function ftthCoordText(lat, lon) {
            if (lat == null || lon == null) return '—';
            return Number(lat).toFixed(6) + ', ' + Number(lon).toFixed(6);
        }

        function ftthShowDetail(m) {
            /* ONU (wireless / hotspot) → kartu ONU baru */
            if (m && ftthIsOnuMarker(m)) {
                ftthShowCustomer(m);
                return;
            }
            /* OLT → gaya kartu ONU */
            if (m && String(m.type || '').toUpperCase() === 'OLT') {
                ftthShowCustomer(m);
                return;
            }
            var activeMk = ftthActiveMarker;
            ftthCloseAllCards();
            ftthActiveMarker = activeMk;
            ftthDetailData = m;
            var color = ftthDeviceColor(m.type);
            document.querySelector('#ftthDetailCard .ftth-detail-head').classList.remove('ftth-detail-head--onu');
            document.getElementById('ftthDetailCard').classList.remove('ftth-odc-card');
            document.getElementById('ftthDetailBadge').style.display = '';
            document.getElementById('ftthDetailName').style.display = '';
            document.getElementById('ftthDetailBadge').textContent = String(m.type).toUpperCase();
            document.getElementById('ftthDetailBadge').style.background = color;
            document.getElementById('ftthDetailName').textContent = m.label;

            /* Always restore default body HTML first (ODC replaces it) */
            var body = document.querySelector('#ftthDetailCard .ftth-detail-body');
            if (!document.getElementById('ftthDetailStatus')) {
                body.innerHTML =
                    '<span class="ftth-device-row-status" id="ftthDetailStatus">-</span>' +
                    '<div class="ftth-detail-row" id="ftthDetailLoc"><i class="fa-solid fa-location-dot"></i><span>-</span></div>' +
                    '<div class="ftth-detail-row" id="ftthDetailCoords"><i class="fa-solid fa-map-pin"></i><span>-</span></div>' +
                    '<div class="ftth-detail-attrs" id="ftthDetailAttrs"></div>' +
                    '<div class="ftth-detail-notes" id="ftthDetailNotes" hidden></div>' +
                    '<div class="ftth-detail-live" id="ftthDetailLive" hidden></div>' +
                    '<div class="ftth-detail-actions" id="ftthDetailActions"></div>' +
                    '<div class="ftth-detail-log" id="ftthDetailLog" hidden></div>';
            }

            /* ── ODC / ODP-specific card ── */
            var mType = String(m.type).toUpperCase();
            if (mType === 'ODC' || mType === 'ODP' || mType === 'OTB') {
                var isOtb = mType === 'OTB';
                var isOnline = m.status === 'online';
                var lineClass = isOnline ? '' : ' offline';
                var statusText = isOnline ? 'ONLINE' : 'OFFLINE';
                var statusColor = isOnline ? 'green' : '';

                var attrs = m.attributes || {};
                var mgmtCore = Number(attrs.management_core) === 1;
                var jumlahPon = attrs.jumlah_pon || '';
                var nomorPon = attrs.nomor_pon || '';
                var warnaCore = attrs.warna_core || '';
                var parentInduk = attrs.induk || '';
                var jarak = attrs.jarak || attrs.distance || '';

                var mgmtLabel = mgmtCore
                    ? ftthT('odc.mgmt_aktif') + (nomorPon ? ' (' + ftthPonLabel(nomorPon) + (warnaCore ? '-' + ftthCoreColorName(warnaCore) : '') + ')' : '')
                    : ftthT('odc.mgmt_nonaktif');

                /* hide default header elements */
                document.getElementById('ftthDetailStatus').innerHTML = '';
                document.getElementById('ftthDetailLoc').innerHTML = '';
                document.getElementById('ftthDetailCoords').innerHTML = '';
                document.getElementById('ftthDetailAttrs').innerHTML = '';
                document.getElementById('ftthDetailNotes').hidden = true;

                /* hide default header (grip/badge/name/close) — ODC has its own */
                var detailHead = document.querySelector('#ftthDetailCard .ftth-detail-head');
                detailHead.style.height = '0';
                detailHead.style.overflow = 'hidden';
                detailHead.style.padding = '0';
                detailHead.style.margin = '0';
                detailHead.style.border = 'none';
                detailHead.style.minHeight = '0';
                detailHead.style.cursor = 'grab';

                /* apply ODC card class */
                document.getElementById('ftthDetailCard').classList.add('ftth-odc-card');

                /* Show card INSTANTLY with known data + "..." for async fields */
                body.innerHTML =
                    '<div class="ftth-odc-head">' +
                        '<div class="ftth-odc-line' + lineClass + '"></div>' +
                        '<span class="ftth-odc-head-name">' + escapeHtml(m.label) + '</span>' +
                        '<span class="ftth-odc-badge">' + mType + '</span>' +
                        '<button type="button" class="ftth-odc-close" onclick="ftthCloseDetail()" title="Tutup"><i class="fa-solid fa-xmark"></i></button>' +
                    '</div>' +
                    '<div class="ftth-odc-topo"><i class="fa-solid fa-network-wired"></i> ' + escapeHtml(parentInduk || '—') + '</div>' +
                    '<div class="ftth-odc-body">' +
                        '<div class="ftth-odc-info-row"><span class="ftth-odc-info-label">' + ftthT('detail.status') + '</span><span class="ftth-odc-info-val ' + statusColor + '" id="ftthOdcStatusVal">' + statusText + '</span></div>' +
                        '<div class="ftth-odc-info-row"><span class="ftth-odc-info-label">Koordinat</span><span class="ftth-odc-info-val blue">' + ftthCoordText(m.lat, m.lon) + '</span></div>' +
                        (m.ip_address ? '<div class="ftth-odc-info-row"><span class="ftth-odc-info-label">IP</span><span class="ftth-odc-info-val blue">' + escapeHtml(m.ip_address) + '</span></div>' : '') +
                        (isOtb ? '' :
                        '<div class="ftth-odc-info-row"><span class="ftth-odc-info-label">' + ftthT('odc.port_usage') + '</span><span class="ftth-odc-info-val green" id="ftthOdcPortVal">...</span></div>' +
                        '<div class="ftth-odc-info-row"><span class="ftth-odc-info-label">' + ftthT('odc.onu_per_jalur') + '</span><span class="ftth-odc-info-val green" id="ftthOdcOnuTotal">...</span></div>' +
                        '<div class="ftth-odc-info-row"><span class="ftth-odc-info-label">' + ftthT('odc.mgmt_core') + '</span><span class="ftth-odc-info-val blue" id="ftthOdcMgmtVal">' + mgmtLabel + '</span></div>') +
                    '</div>' +
                    (jarak ? '<div class="ftth-odc-distance"><span>' + escapeHtml(String(jarak)) + '</span></div>' : '') +
                    '<div class="ftth-odc-btns-bottom">' +
                        '<button type="button" class="ftth-odc-btn-lg blue" onclick="ftthOdcAction(\'edit\')"><i class="fa-solid fa-pen"></i> ' + ftthT('odc.edit') + '</button>' +
                        '<a class="ftth-odc-btn-lg green-dark" target="_blank" rel="noopener" id="ftthOdcMapLink" href="#"><i class="fa-solid fa-map-location-dot"></i> ' + ftthT('odc.maps') + '</a>' +
                        '<button type="button" class="ftth-odc-btn-lg red" onclick="ftthOdcAction(\'hapus\')"><i class="fa-solid fa-trash-can"></i> HAPUS</button>' +
                    '</div>' +
                    '<button type="button" class="ftth-odc-btn-lg cyan ftth-kabel-btn" onclick="ftthCablePropsOpenForDetail()"><i class="fa-solid fa-route"></i> Edit Kabel</button>';

                if (m.lat != null && m.lon != null) {
                    document.getElementById('ftthOdcMapLink').href = 'https://www.google.com/maps?q=' + m.lat + ',' + m.lon;
                }

                ftthCardDocked = true;
                document.getElementById('ftthDetailCard').hidden = false;
                ftthPositionDetailCard();

                /* async load stats (hanya ODC/ODP — OTB tidak punya endpoint stats) */
                if (!isOtb) mtApi('/noc/features/map/' + mType.toLowerCase() + '-stats/' + m.id, 'GET').then(function(r) {
                    if (!r.data || !r.data.ok) return;
                    var s = r.data;
                    var statusVal = document.getElementById('ftthOdcStatusVal');
                    if (statusVal && s.status) {
                        var isOn = s.status === 'online';
                        statusVal.textContent = isOn ? 'ONLINE' : 'OFFLINE';
                        statusVal.className = 'ftth-odc-info-val' + (isOn ? ' green' : '');
                        var lineEl = document.querySelector('.ftth-odc-line');
                        if (lineEl) lineEl.className = 'ftth-odc-line' + (isOn ? '' : ' offline');
                    }
                    var portVal = document.getElementById('ftthOdcPortVal');
                    if (portVal) portVal.textContent = s.port_used + ' / ' + s.port_total + ' (Sisa: ' + s.sisa + ')';
                    var onuTotal = document.getElementById('ftthOdcOnuTotal');
                    if (onuTotal) onuTotal.textContent = s.onu_total || 0;
                    if (!jarak && s.distance) {
                        var distEl = document.querySelector('.ftth-odc-distance span');
                        if (distEl) distEl.textContent = s.distance;
                    }
                    ftthPositionDetailCard();
                }).catch(function() {});

                return;
            }

            /* ── Generic card for other device types ── */
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
            } else if (m.lat != null && m.lon != null) {
                locEl.innerHTML = '<i class="fa-solid fa-location-dot"></i><span>' + ftthT('detail.mencari_alamat') + '</span>';
                ftthCardGeocode(m.lat, m.lon);
            } else {
                locEl.innerHTML = '<i class="fa-solid fa-location-dot"></i><span>—</span>';
            }
            document.getElementById('ftthDetailCoords').innerHTML =
                '<i class="fa-solid fa-map-pin"></i><span>' + ftthCoordText(m.lat, m.lon) + '</span>';

            var rows = [];
            if (m.parent) rows.push([ftthT('detail.induk'), m.parent]);
            if (m.detail) rows.push([ftthT('detail.detail_label'), m.detail]);
            if (m.brand) rows.push([ftthT('detail.brand'), m.brand]);
            if (m.model) rows.push([ftthT('detail.model'), m.model]);
            if (m.capacity) rows.push([ftthT('detail.kapasitas'), m.capacity]);
            if (m.ip_address) rows.push(['IP', m.ip_address]);
            var attrs = m.attributes || {};
            if (typeof attrs === 'object') {
                Object.keys(attrs).forEach(function(k) {
                    var v = attrs[k];
                    if (v === null || v === undefined || v === '') return;
                    if (k === 'management_core') {
                        if (Number(v) === 1) rows.push([ftthT('detail.mgmt_core_label'), ftthT('detail.ya')]);
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
                    '<button type="button" class="ftth-detail-del" onclick="ftthDetailDelete()" title="Hapus" data-i18n="detail.hapus_btn"><i class="fa-solid fa-trash-can"></i></button>';
                actions.innerHTML += '<button type="button" class="ftth-modal-btn" onclick="ftthCableEditStartForDetail()"><i class="fa-solid fa-route"></i> Edit Kabel</button>';
            }

            ftthCardDocked = true;
            document.getElementById('ftthDetailCard').hidden = false;
            ftthPositionDetailCard();
        }

        function ftthCloseDetail() {
            var el = document.getElementById('ftthDetailCard');
            if (el) {
                el.hidden = true;
                el.style.visibility = '';
                el.classList.remove('ftth-odc-card');
            }
            var head = document.querySelector('#ftthDetailCard .ftth-detail-head');
            if (head) {
                head.style.height = '';
                head.style.overflow = '';
                head.style.padding = '';
                head.style.margin = '';
                head.style.border = '';
                head.style.minHeight = '';
                head.style.cursor = '';
            }
            ftthDetailData = null;
            ftthActiveMarker = null;
            if (ftthActiveIconEl) { ftthActiveIconEl.classList.remove('ftth-marker-active'); ftthActiveIconEl = null; }
            ftthCardDocked = false;
            ftthCardDragging = false;
            ftthOnuStopTraffic();
        }

        function ftthOdcAction(action) {
            var m = ftthDetailData;
            if (!m || m.source !== 'device') return;
            if (action === 'salin') {
                var attrs = m.attributes || {};
                var mgmtCore = Number(attrs.management_core) === 1;
                var lines = [
                    m.label,
                    ftthT('detail.status') + ': ' + (m.status || '-'),
                    ftthT('odc.port_usage') + ': ...',
                    ftthT('odc.mgmt_core') + ': ' + (mgmtCore ? ftthT('odc.mgmt_aktif') : ftthT('odc.mgmt_nonaktif')),
                    m.parent ? ftthT('detail.induk') + ': ' + m.parent : '',
                    m.ip_address ? 'IP: ' + m.ip_address : '',
                    m.brand ? ftthT('detail.brand') + ': ' + m.brand : '',
                    m.model ? ftthT('detail.model') + ': ' + m.model : '',
                    m.capacity ? ftthT('detail.kapasitas') + ': ' + m.capacity : '',
                    m.lat != null && m.lon != null ? 'Maps: https://www.google.com/maps?q=' + m.lat + ',' + m.lon : ''
                ].filter(Boolean).join('\n');
                ftthCopyText(lines, ftthT('odc.salin'));
            } else if (action === 'edit') {
                ftthEditFromDetail();
            } else if (action === 'hapus') {
                ftthDetailDelete();
            }
        }

        function ftthDetailDuplicate() {
            var m = ftthDetailData;
            if (!m || m.source !== 'device') return;
            if (!confirm(ftthT('confirm.hapus_perangkat') + ' "' + m.label + '"?')) return;
            var attrs = m.attributes || {};
            ftthOpenAddDevice({
                type: String(m.type).toLowerCase(),
                name: m.label + ' (copy)',
                notes: m.notes || '',
                capacity: m.capacity || '',
                ip: m.ip_address || '',
                pppoe: attrs.pppoe_user || '',
                parent: attrs.induk || '',
                lat: m.lat,
                lon: m.lon,
                location: m.location || ''
            });
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
                ftthToast(ftthT('toast.status_change_fail'), 'error');
            });
        }

        function ftthDetailDelete() {
            var m = ftthDetailData;
            if (!m || m.source !== 'device') return;
            if (!confirm(ftthT('confirm.hapus_perangkat') + ' "' + m.label + '"?')) return;
            mtApi('/noc/features/map/device/delete', 'POST', { id: m.id }).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthCloseDetail();
                    loadMapMarkers();
                    ftthToast(r.data.message || 'Perangkat dihapus', 'ok');
                } else {
                    ftthToast((r.data && r.data.error) || 'Gagal menghapus perangkat', 'error');
                }
            }).catch(function() {
                ftthToast(ftthT('toast.device_delete_fail'), 'error');
            });
        }

        /* ── Card ONU pelanggan (klik marker pelanggan) ── */

        var ftthCustDetail = null;
        var ftthAcsInfo = null;
        var ftthAcsShownAt = 0;
        var ftthCustBusy = false;

        function ftthLogRow(cls, text) {
            var el = document.getElementById('ftthDetailLog');
            if (!el) return;
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
                ftthToast(ftthT('toast.copy_fail'), 'error');
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

        function ftthHumanUptime(sec) {
            sec = Number(sec);
            if (!isFinite(sec) || sec <= 0) return '-';
            var d = Math.floor(sec / 86400);
            var h = Math.floor((sec % 86400) / 3600);
            var m = Math.floor((sec % 3600) / 60);
            if (d > 0) return d + 'd ' + h + 'h ' + m + 'm';
            if (h > 0) return h + 'h ' + m + 'm';
            return m + 'm';
        }

        function ftthOnuTypeLabel(m) {
            var t = String((m && (m.onu_type || m.customer_type || m.type)) || '').toLowerCase();
            if (t.indexOf('hotspot') !== -1) return 'HOTSPOT';
            if (t.indexOf('ppp') !== -1) return 'PPPoE';
            if (t.indexOf('wireless') !== -1) return 'WIRELESS';
            if (t) return t.toUpperCase();
            return 'ONU';
        }

        var FTTH_ONU_CARD_HTML =
            '<div class="ftth-onu-card">' +
              '<span class="ftth-onu-type" id="ftthOnuType"></span>' +
              '<span class="ftth-onu-port" id="ftthOnuPort"></span>' +
              '<div class="ftth-onu-ipbox">' +
                '<i class="fa-solid fa-network-wired"></i>' +
                '<span id="ftthOnuIp">-</span>' +
                '<a class="ftth-onu-globe" id="ftthOnuGlobe" target="_blank" rel="noopener" href="#"><i class="fa-solid fa-globe"></i></a>' +
                 '<button class="ftth-onu-ping" onclick="ftthCustPing()"><i class="fa-solid fa-terminal"></i> Ping</button>' +
              '</div>' +
              '<div class="ftth-onu-statusrow"><span>Status</span><b id="ftthOnuStatus">-</b></div>' +
               '<div class="ftth-onu-statusrow"><span>Koordinat</span><b id="ftthOnuCoords">-</b></div>' +
               '<div class="ftth-onu-box ftth-onu-acs" id="ftthOnuAcsBox">' +
                  '<div class="ftth-onu-acs-head"><span class="ftth-onu-acs-title" id="ftthOnuAcsState"></span><span class="ftth-onu-acs-loading" id="ftthOnuAcsLoading"><span class="ftth-a-loader" style="display:inline-block;width:14px;height:14px;vertical-align:middle;margin:0 7px 0 0"><svg viewBox="-2 -2 58 52" style="width:100%;height:100%"><path class="ftth-a-chevron" d="M6 38 L26 8 L46 38"/><g class="ftth-a-check-group"><path class="ftth-a-check" d="M22 26 C10 30 16 44 28 34 C36 26 42 20 44 19"/><circle class="ftth-a-tip" cx="50" cy="17" r="2.5"/></g></svg></span> Memuat data ACS…</span><span class="ftth-onu-uptime" id="ftthOnuUptime">Up: -</span></div>' +
                 '<div class="ftth-onu-acs-content" id="ftthOnuAcsContent" hidden>' +
                   '<div class="ftth-onu-atten"><div class="ftth-onu-atten-item" id="ftthOnuRxAwalBox"><span>Redaman Awal</span><b id="ftthOnuRxAwal">-</b></div><div class="ftth-onu-atten-item" id="ftthOnuRxNowBox"><span>Redaman Skg</span><b id="ftthOnuRxNow">-</b></div></div>' +
                   '<div class="ftth-onu-wifi" id="ftthOnuWifiBox" hidden>' +
                     '<div class="ftth-onu-wifi-row"><span>SSID</span><b id="ftthOnuSsid">-</b></div>' +
                     '<div class="ftth-onu-wifi-row"><span>Password</span><b id="ftthOnuPass">-</b><i class="fa-solid fa-eye ftth-onu-eye" onclick="ftthOnuTogglePass()"></i></div>' +
                   '</div>' +
                   '<div class="ftth-onu-clients" id="ftthOnuClientsBox" hidden><span>Client WLAN Aktif: <b id="ftthOnuWlan">-</b></span><span>Client LAN Aktif: <b id="ftthOnuLan">-</b></span></div>' +
                   '<div class="ftth-onu-acs-actions" id="ftthOnuAcsActions" hidden><button class="ftth-onu-btn-ganti" onclick="ftthOnuOpenGantiWifi()"><i class="fa-solid fa-wifi"></i> Ganti WiFi</button><button class="ftth-onu-btn-reboot" onclick="ftthCustReboot()"><i class="fa-solid fa-power-off"></i> Reboot</button></div>' +
                   '<div class="ftth-onu-ganti" id="ftthOnuGantiBox" hidden>' +
                     '<input type="text" id="ftthOnuGantiSsid" placeholder="SSID baru">' +
                     '<input type="text" id="ftthOnuGantiPass" placeholder="Password baru">' +
                     '<button onclick="ftthOnuSaveGantiWifi()"><i class="fa-solid fa-check"></i> Simpan</button>' +
                     '<button onclick="ftthOnuCloseGantiWifi()"><i class="fa-solid fa-xmark"></i></button>' +
                   '</div>' +
                 '</div>' +
               '</div>' +
                 '<div class="ftth-onu-box ftth-onu-traffic"><div class="ftth-onu-traffic-head"><span class="ftth-onu-traffic-title">Live Traffic</span><span class="ftth-onu-tx"><i></i> TX: <b id="ftthOnuTxVal">-</b></span><span class="ftth-onu-rx"><i></i> RX: <b id="ftthOnuRxVal">-</b></span></div><div class="ftth-onu-traffic-chart"><canvas id="ftthOnuTrafficChart"></canvas></div><div class="ftth-onu-traffic-status" id="ftthOnuTrafficStatus"></div><div class="ftth-onu-hs-clients" id="ftthOnuHsClients" hidden></div></div>' +
              '<div class="ftth-onu-footer" id="ftthOnuTotal">286 M</div>' +
              '<div class="ftth-onu-bottom" id="ftthOnuBottom"></div>' +
              '<div class="ftth-detail-log" id="ftthDetailLog" hidden></div>' +
            '</div>';

        var ftthOnuPassVal = '';
        var ftthOnuTrafficTimer = null;
        var ftthOnuTrafficChart = null;
var ftthTowerTickAt = null;

        function ftthOnuAttenClass(db) {
            if (db === null || db === undefined || isNaN(db)) return 'ftth-onu-atten-item';
            var v = parseFloat(db);
            if (v >= -25) return 'ftth-onu-atten-item ftth-onu-atten-good';
            if (v >= -30) return 'ftth-onu-atten-item ftth-onu-atten-warn';
            return 'ftth-onu-atten-item ftth-onu-atten-bad';
        }

        function ftthResolveCid(m) {
            if (!m) return null;
            if (m.source === 'customer') return m.id;
            if (m.source === 'device') return m.customer_id || null;
            return null;
        }

        function ftthCustRenderActions(m, d) {
            d = d || ftthCustDetail || {};
            /* Maps: dari detail pelanggan, atau dari koordinat marker sendiri (device ONU selalu punya lat/lon) */
            var maps = d.maps || (m && m.lat != null && m.lon != null ? 'https://www.google.com/maps?q=' + m.lat + ',' + m.lon : '');
            var wa = d.wa || '';
            var edit = d.edit || '';
            /* Bila device belum ter-link ke pelanggan, arahkan WA & EDIT ke halaman pencarian pelanggan */
            var custSearch = '/customers' + (m && m.label ? ('?q=' + encodeURIComponent(m.label)) : '');

            /* Perangkat infrastruktur (OLT): aksi perangkat, bukan pelanggan */
            if (m && String(m.type).toUpperCase() === 'OLT') {
                var actsOlt = '<div class="ftth-odc-btns" style="grid-template-columns:1fr 1fr 1fr; margin-top:10px">';
                actsOlt += '<button type="button" class="ftth-odc-btn blue" onclick="ftthEditFromDetail()"><i class="fa-solid fa-pen"></i> Edit</button>';
                if (maps) actsOlt += '<a class="ftth-odc-btn green-dark" target="_blank" rel="noopener" href="' + escapeHtml(maps) + '"><i class="fa-solid fa-map-location-dot"></i> Maps</a>';
                else actsOlt += '<button type="button" class="ftth-odc-btn green-dark" disabled style="background:#475569;cursor:not-allowed"><i class="fa-solid fa-map-location-dot"></i> Maps</button>';
                actsOlt += '<button type="button" class="ftth-odc-btn red" onclick="ftthDetailDelete()"><i class="fa-solid fa-trash-can"></i> Hapus</button>';
                actsOlt += '</div>';
                actsOlt += '<button type="button" class="ftth-odc-btn cyan ftth-kabel-btn" onclick="ftthCablePropsOpenForDetail()" style="margin-bottom:4px"><i class="fa-solid fa-route"></i> Edit Kabel</button>';
                var elOlt = document.getElementById('ftthOnuBottom');
                if (elOlt) elOlt.innerHTML = actsOlt;
                return;
            }

            var acts = '';
            acts += '<div class="ftth-odc-btns">';
            acts += '<a class="ftth-odc-btn blue" target="_blank" rel="noopener" href="' + escapeHtml(edit || custSearch) + '"><i class="fa-solid fa-pen"></i> Edit</a>';
            if (maps) acts += '<a class="ftth-odc-btn green-dark" target="_blank" rel="noopener" href="' + escapeHtml(maps) + '"><i class="fa-solid fa-map-location-dot"></i> Maps</a>';
            else acts += '<button type="button" class="ftth-odc-btn green-dark" disabled style="background:#475569;cursor:not-allowed"><i class="fa-solid fa-map-location-dot"></i> Maps</button>';
            acts += '<a class="ftth-odc-btn green-light" target="_blank" rel="noopener" href="' + escapeHtml(wa || custSearch) + '"><i class="fa-brands fa-whatsapp"></i> WA</a>';
            acts += '<button type="button" class="ftth-odc-btn red" onclick="ftthCustDelete()"><i class="fa-solid fa-trash-can"></i> Hapus</button>';
            acts += '</div>';
            acts += '<button type="button" class="ftth-odc-btn cyan ftth-kabel-btn" onclick="ftthCablePropsOpenForDetail()"><i class="fa-solid fa-route"></i> Edit Kabel</button>';
            var el = document.getElementById('ftthOnuBottom');
            if (el) el.innerHTML = acts;
        }

        function ftthCustDelete() {
            var m = ftthDetailData;
            var cid = ftthResolveCid(m);
            if (!cid) return;
            if (!confirm(ftthT('confirm.hapus_pelanggan') + ' "' + (m ? m.label : '') + '"?')) return;
            ftthClearLog();
            ftthLogRow('info', ftthT('sync.menghapus'));
            mtApi('/noc/features/map/customer/delete', 'POST', { id: cid }).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthToast(r.data.message || 'Pelanggan dihapus', 'ok');
                    ftthCloseAllCards();
                    loadMapMarkers();
                } else {
                    ftthToast((r.data && r.data.error) || ftthT('toast.customer_delete_fail'), 'error');
                }
            }).catch(function() {
                ftthToast(ftthT('toast.customer_delete_fail'), 'error');
            });
        }

        function ftthShowCustomer(m) {
            var activeMk = ftthActiveMarker;
            ftthCloseAllCards();
            ftthActiveMarker = activeMk;
            ftthDetailData = m;
            ftthCustDetail = null;
            ftthAcsInfo = null;
            ftthCustBusy = false;
            ftthClearLog();

            document.getElementById('ftthDetailBadge').textContent = ftthOnuTypeLabel(m);
            document.getElementById('ftthDetailBadge').style.background = 'rgba(59,130,246,0.2)';
            document.getElementById('ftthDetailBadge').style.color = '#93c5fd';
            document.getElementById('ftthDetailBadge').style.borderColor = 'rgba(59,130,246,0.45)';
            document.getElementById('ftthDetailBadge').style.display = '';
            document.getElementById('ftthDetailName').textContent = m.label;
            document.getElementById('ftthDetailName').style.display = '';

            document.querySelector('#ftthDetailCard .ftth-detail-head').classList.add('ftth-detail-head--onu');
            document.getElementById('ftthDetailCard').classList.remove('ftth-odc-card');

            var body = document.querySelector('#ftthDetailCard .ftth-detail-body');
            if (body) {
                body.innerHTML = FTTH_ONU_CARD_HTML;
                var oc = document.getElementById('ftthOnuCoords');
                if (oc) oc.textContent = ftthCoordText(m.lat, m.lon);
                /* Status langsung dari data marker — jangan tunggu detail async */
                var stEl0 = document.getElementById('ftthOnuStatus');
                if (stEl0) {
                    var st0 = m.onu_status || m.status || '';
                    stEl0.className = st0 === 'online' ? 'online' : (st0 === 'offline' ? 'offline' : 'other');
                    stEl0.textContent = st0 ? String(st0).toUpperCase() : '…';
                }
                ftthOnuTrafficChart = null;
                ftthAcsShownAt = Date.now();
            }

            ftthCustRenderActions(m, null);

            ftthCardDocked = true;
            document.getElementById('ftthDetailCard').hidden = false;
            ftthPositionDetailCard();

            ftthCustLoad();
            ftthOnuStartTraffic();
        }

        function ftthCustLoad() {
            var m = ftthDetailData;
            var cid = ftthResolveCid(m);
            if (!cid) { ftthCustRenderDevice(m); return; }
            ftthCustBusy = true;
            ftthCustRenderActions(m, ftthCustDetail);
            mtApi('/noc/features/map/customer/detail?id=' + encodeURIComponent(cid), 'GET').then(function(r) {
                ftthCustBusy = false;
                var d = (r.data && r.data.ok) ? r.data : null;
                if (!d) {
                    ftthLogRow('err', (r.data && r.data.error) || ftthT('log.gagal_load_detail'));
                    ftthOnuAcsResolve(false);
                    ftthCustRenderActions(m, null);
                    return;
                }
                ftthCustDetail = d;
                ftthCustRender(m, d);
            }).catch(function() {
                ftthCustBusy = false;
                ftthLogRow('err', ftthT('log.gagal_load_network'));
                ftthOnuAcsResolve(false);
                ftthCustRenderActions(m, null);
            });
        }

        function ftthCustRenderDevice(m) {
            /* Fallback: perangkat ONU tanpa pelanggan terhubung (mis. wireless yang belum di-link) */
            document.getElementById('ftthDetailName').textContent = m.label || '-';
            var typeParts = [];
            if (m.detail) typeParts.push(m.detail);
            if (m.onu_type) typeParts.push(m.onu_type);
            document.getElementById('ftthOnuType').textContent = typeParts.join(' · ') || '-';
            document.getElementById('ftthDetailBadge').textContent = ftthOnuTypeLabel(m);

            var ip = (m.ip_address || (m.attributes && m.attributes.ip) || '-');
            document.getElementById('ftthOnuIp').textContent = ip;
            var globe = document.getElementById('ftthOnuGlobe');
            if (globe) globe.href = (ip && ip !== '-') ? ('http://' + ip) : '#';

            var status = m.status || 'offline';
            var stCls = status === 'online' ? 'online' : (status === 'offline' ? 'offline' : 'other');
            var stEl = document.getElementById('ftthOnuStatus');
            if (stEl) { stEl.className = stCls; stEl.textContent = String(status).toUpperCase(); }

            var uptime = document.getElementById('ftthOnuUptime');
            if (uptime) uptime.textContent = 'Up: -';

            document.getElementById('ftthOnuRxAwal').textContent = '-';
            document.getElementById('ftthOnuRxNow').textContent = '-';
            document.getElementById('ftthOnuRxAwalBox').className = 'ftth-onu-atten-item';
            document.getElementById('ftthOnuRxNowBox').className = 'ftth-onu-atten-item';

            /* Tampilkan loader animasi dulu, lalu tentukan status ACS secara asinkron */
            setTimeout(function() {
                if (ftthDetailData === m) ftthOnuAcsResolve(false);
            }, 800);

            var totalEl = document.getElementById('ftthOnuTotal');
            if (totalEl) totalEl.textContent = '286 M';

            ftthCustRenderActions(m, null);
            ftthPositionDetailCard();
        }

        function ftthOnuAcsApply(detected) {
            var loading = document.getElementById('ftthOnuAcsLoading');
            if (loading) loading.hidden = true;
            var content = document.getElementById('ftthOnuAcsContent');
            if (content) content.hidden = !detected;
            var state = document.getElementById('ftthOnuAcsState');
            if (state) {
                state.textContent = detected ? 'ACS Aktif' : 'ACS Tidak Terdeteksi';
                state.className = 'ftth-onu-acs-title' + (detected ? '' : ' off');
            }
        }

        function ftthOnuAcsResolve(detected) {
            var minMs = 800;
            var elapsed = Date.now() - (ftthAcsShownAt || Date.now());
            var wait = Math.max(0, minMs - elapsed);
            setTimeout(function() { ftthOnuAcsApply(detected); }, wait);
        }

        function ftthCustRender(m, d) {
            var c = d.customer || {};
            var onu = d.onu || {};
            var sess = d.session || {};

            document.getElementById('ftthDetailName').textContent = c.name || m.label || '-';
            var typeParts = [];
            if (m.customer_type) typeParts.push(m.customer_type);
            if (m.onu_type) typeParts.push(m.onu_type);
            if (onu.acs_product_class || onu.acs_manufacturer) typeParts.push(onu.acs_product_class || onu.acs_manufacturer);
            document.getElementById('ftthOnuType').textContent = typeParts.join(' · ') || '-';
            document.getElementById('ftthDetailBadge').textContent = ftthOnuTypeLabel(m);

            var ip = sess.ip || onu.acs_ip || m.ip_address || (m.attributes && m.attributes.ip) || '-';
            document.getElementById('ftthOnuIp').textContent = ip;
            var globe = document.getElementById('ftthOnuGlobe');
            globe.href = (ip && ip !== '-') ? ('http://' + ip) : '#';

            var portOlt = (onu.slot || onu.port) ? ((onu.slot || '-') + '/' + (onu.port || '-')) : '-';
            document.getElementById('ftthOnuPort').textContent = portOlt;

            /* Status ONU di peta konsisten ONLINE/OFFLINE: prioritas status
               realtime (OLT/PPP), lalu hasil resolve marker. Status billing
               'active' TIDAK dipakai untuk yang sudah ada di peta */
            var status = onu.status || m.onu_status || m.status || 'offline';
            var stEl = document.getElementById('ftthOnuStatus');
            var stCls = status === 'online' ? 'online' : (status === 'offline' ? 'offline' : 'other');
            stEl.className = stCls;
            stEl.textContent = onu.status_label || String(status).toUpperCase();

            document.getElementById('ftthDetailBadge').textContent = ftthOnuTypeLabel(m);

            var acsDetected = !!onu.acs_device_id;
            ftthOnuAcsResolve(acsDetected);
            document.getElementById('ftthOnuUptime').textContent = 'Up: ' + ftthHumanUptime(sess.uptime || onu.uptime);

            var rxAwalBox = document.getElementById('ftthOnuRxAwalBox');
            var txv = (onu.tx_power !== null && onu.tx_power !== undefined) ? parseFloat(onu.tx_power) : null;
            document.getElementById('ftthOnuRxAwal').textContent = txv !== null ? (txv + ' dBm') : '-';
            rxAwalBox.className = ftthOnuAttenClass(txv);

            var rxNowBox = document.getElementById('ftthOnuRxNowBox');
            var rxv = (onu.rx_power !== null && onu.rx_power !== undefined) ? parseFloat(onu.rx_power) : null;
            document.getElementById('ftthOnuRxNow').textContent = rxv !== null ? (rxv + ' dBm') : '-';
            rxNowBox.className = ftthOnuAttenClass(rxv);

            var totalBytes = (sess.bytes_in || 0) + (sess.bytes_out || 0);
            var totalEl = document.getElementById('ftthOnuTotal');
            if (totalEl) totalEl.textContent = totalBytes ? ftthHumanBytes(totalBytes) : '286 M';

            var acsActions = document.getElementById('ftthOnuAcsActions'); if (acsActions) acsActions.hidden = !acsDetected;

            ftthCustRenderActions(m, d);
            ftthPositionDetailCard();
        }

        function ftthOnuLoadAcs() {
            var m = ftthDetailData;
            var cid = ftthResolveCid(m);
            if (!cid) return;
            var onu = (ftthCustDetail && ftthCustDetail.onu) || {};
            if (!onu.acs_device_id) return;
            mtApi('/noc/features/map/customer/acs', 'POST', { id: cid }).then(function(r) {
                if (!r.data || !r.data.ok) return;
                ftthAcsInfo = r.data.acs || {};
                var a = ftthAcsInfo;
                if (a.ssid || a.wifi_password !== undefined) {
                    document.getElementById('ftthOnuWifiBox').hidden = false;
                    document.getElementById('ftthOnuSsid').textContent = a.ssid || '-';
                    ftthOnuPassVal = a.wifi_password || '-';
                    var passEl = document.getElementById('ftthOnuPass');
                    passEl.textContent = '••••••••';
                    passEl.dataset.show = '0';
                }
                if (a.wlan_clients !== undefined || a.lan_clients !== undefined) {
                    document.getElementById('ftthOnuClientsBox').hidden = false;
                    document.getElementById('ftthOnuWlan').textContent = (a.wlan_clients != null) ? a.wlan_clients : '-';
                    document.getElementById('ftthOnuLan').textContent = (a.lan_clients != null) ? a.lan_clients : '-';
                }
                document.getElementById('ftthOnuAcsActions').hidden = false;
            }).catch(function() {});
        }

        function ftthOnuTogglePass() {
            var el = document.getElementById('ftthOnuPass');
            if (!el) return;
            if (el.dataset.show === '1') {
                el.textContent = '••••••••';
                el.dataset.show = '0';
            } else {
                el.textContent = ftthOnuPassVal;
                el.dataset.show = '1';
            }
        }

        function ftthOnuOpenGantiWifi() {
            document.getElementById('ftthOnuGantiBox').hidden = false;
            var a = ftthAcsInfo || {};
            document.getElementById('ftthOnuGantiSsid').value = a.ssid || '';
            document.getElementById('ftthOnuGantiPass').value = '';
        }
        function ftthOnuCloseGantiWifi() {
            document.getElementById('ftthOnuGantiBox').hidden = true;
        }
        function ftthOnuSaveGantiWifi() {
            var m = ftthDetailData;
            var cid = ftthResolveCid(m);
            if (!cid) return;
            var ssid = document.getElementById('ftthOnuGantiSsid').value.trim();
            var pass = document.getElementById('ftthOnuGantiPass').value;
            ftthClearLog();
            ftthLogRow('info', 'Mengubah WiFi...');
            mtApi('/noc/features/map/customer/acs/set', 'POST', { id: cid, ssid: ssid, password: pass }).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthLogRow('ok', r.data.message || 'WiFi diperbarui');
                    ftthOnuCloseGantiWifi();
                    setTimeout(ftthOnuLoadAcs, 4000);
                } else {
                    ftthLogRow('err', (r.data && r.data.error) || 'Gagal');
                }
            }).catch(function() { ftthLogRow('err', 'Gagal set WiFi'); });
        }

        function ftthHumanRate(bps) {
            bps = Math.max(0, bps || 0);
            if (bps >= 1000000000) return (bps / 1000000000).toFixed(2) + ' Gbps';
            if (bps >= 1000000) return (bps / 1000000).toFixed(2) + ' Mbps';
            if (bps >= 1000) return (bps / 1000).toFixed(1) + ' Kbps';
            return Math.round(bps) + ' bps';
        }

        function ftthOnuInitChart() {
            var ctx = document.getElementById('ftthOnuTrafficChart');
            if (!ctx || ftthOnuTrafficChart || typeof Chart === 'undefined') return;
            ftthOnuTrafficChart = new Chart(ctx, {
                type: 'line',
                data: { labels: [], datasets: [
                    { label: 'Rx', data: [], borderColor: '#22c55e', backgroundColor: 'rgba(34,197,94,0.15)', fill: true, tension: 0.3, pointRadius: 0, borderWidth: 2 },
                    { label: 'Tx', data: [], borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.15)', fill: true, tension: 0.3, pointRadius: 0, borderWidth: 2 }
                ]},
                options: { animation: false, responsive: true, maintainAspectRatio: false,
                    scales: { x: { display: false, grid: { display: false } }, y: { ticks: { color: '#94a3b8', callback: function(v) { return ftthHumanRate(v); } }, grid: { color: 'rgba(148,163,184,0.1)' } } },
                    plugins: { legend: { display: false } } }
            });
        }

        /* Ubah riwayat counter server [{t,in,out}] menjadi laju bps per sampel */
        function ftthHistRates(h) {
            var rx = [], tx = [], i, dt;
            for (i = 1; i < h.length; i++) {
                dt = h[i].t - h[i - 1].t;
                if (dt <= 0 || h[i].in < h[i - 1].in || h[i].out < h[i - 1].out) continue;
                rx.push(Math.max(0, ((h[i].in - h[i - 1].in) / dt) * 8));
                tx.push(Math.max(0, ((h[i].out - h[i - 1].out) / dt) * 8));
            }
            return [rx, tx];
        }

        /* Isi seluruh buffer chart Live Traffic card ONU sekaligus */
        function ftthOnuChartFill(rxArr, txArr) {
            if (!ftthOnuTrafficChart) return;
            var lbl = [], i;
            for (i = 0; i < rxArr.length; i++) lbl.push('');
            ftthOnuTrafficChart.data.labels = lbl;
            ftthOnuTrafficChart.data.datasets[0].data = rxArr;
            ftthOnuTrafficChart.data.datasets[1].data = txArr;
            ftthOnuTrafficChart.update();
        }

        function ftthOnuTick() {
            var m = ftthDetailData;
            var cid = ftthResolveCid(m);
            var statusEl = document.getElementById('ftthOnuTrafficStatus');
            var setStatus = function(txt, cls) {
                if (!statusEl) return;
                statusEl.textContent = txt || '';
                statusEl.className = 'ftth-onu-traffic-status' + (cls ? ' ' + cls : '');
            };
            if (!cid) {
                /* OLT: status & trafik agregat dari endpoint olt-live.
                   Rate dihitung server dan riwayatnya di-cache — satu respons
                   langsung mengisi seluruh chart, tanpa throttle klien. */
                if (m && String(m.type).toUpperCase() === 'OLT') {
                    mtApi('/noc/features/map/olt-live/' + m.id, 'GET').then(function(r) {
                        var d = r.data || {};
                        var txVal = document.getElementById('ftthOnuTxVal');
                        var rxVal = document.getElementById('ftthOnuRxVal');
                        if (!d.ok || !d.online) {
                            setStatus('OLT tidak Terhubung', 'off');
                            if (txVal) txVal.textContent = '-';
                            if (rxVal) rxVal.textContent = '-';
                            return;
                        }
                        setStatus('Live', 'live');
                        var down = d.bw_down != null ? parseFloat(d.bw_down) : null;
                        var up = d.bw_up != null ? parseFloat(d.bw_up) : null;
                        if (txVal) txVal.textContent = up != null ? ftthHumanRate(up) : '-';
                        if (rxVal) rxVal.textContent = down != null ? ftthHumanRate(down) : '-';
                        /* history: [{t, down, up}] — sudah berupa rate bps */
                        var h = Array.isArray(d.history) ? d.history : [];
                        var rx = [], tx = [], i;
                        for (i = 0; i < h.length; i++) { rx.push(h[i].down || 0); tx.push(h[i].up || 0); }
                        ftthOnuChartFill(rx.slice(-40), tx.slice(-40));
                    }).catch(function() { setStatus('OLT tidak Terhubung', 'off'); });
                    return;
                }
                /* ONU tanpa pelanggan ter-link: cek sesi PPP live di MikroTik.
                   Walau tidak ada Customer di DB, bila sesi aktif maka tampilkan
                   trafik LIVE (bukan "Tanpa pelanggan terhubung"). */
                var pppUser = (m && m.attributes && m.attributes.pppoe_user)
                    ? m.attributes.pppoe_user
                    : (m && m.pppoe_username ? m.pppoe_username : null);
                if (!pppUser) {
                    setStatus('Tanpa pelanggan terhubung', 'off');
                    return;
                }
                mtApi('/noc/features/map/mikrotik/pppoe-session?user=' + encodeURIComponent(pppUser), 'GET').then(function(r) {
                    var d = r.data || {};
                    var s = (d.ok && d.found) ? d.session : null;
                    if (!s) {
                        setStatus('Sesi tidak aktif', 'off');
                        var tx2 = document.getElementById('ftthOnuTxVal');
                        var rx2 = document.getElementById('ftthOnuRxVal');
                        if (tx2) tx2.textContent = '-';
                        if (rx2) rx2.textContent = '-';
                        return;
                    }
                    setStatus('Live', 'live');
                    /* Riwayat counter sesi tersimpan di server: laju dihitung
                       dari selisih antar sampel, chart penuh seketika */
                    var pair = ftthHistRates(Array.isArray(d.history) ? d.history : []);
                    var lastRx = pair[0].length ? pair[0][pair[0].length - 1] : null;
                    var lastTx = pair[1].length ? pair[1][pair[1].length - 1] : null;
                    var txVal = document.getElementById('ftthOnuTxVal');
                    var rxVal = document.getElementById('ftthOnuRxVal');
                    if (txVal) txVal.textContent = lastTx === null ? '-' : ftthHumanRate(lastTx);
                    if (rxVal) rxVal.textContent = lastRx === null ? '-' : ftthHumanRate(lastRx);
                    ftthOnuChartFill(pair[0].slice(-40), pair[1].slice(-40));
                }).catch(function() {
                    setStatus('Sesi tidak aktif', 'off');
                });
                return;
            }
            mtApi('/noc/features/map/customer/detail?id=' + encodeURIComponent(cid), 'GET').then(function(r) {
                var d = (r.data && r.data.ok) ? r.data : null;
                if (!d || !d.session) {
                    /* Pelanggan hotspot: bila tak ada sesi login aktif, tampilkan
                       trafik AGREGAT TOWER (total bandwidth interface hotspot). */
                    if (d && d.customer && String(d.customer.type).indexOf('hotspot') === 0) {
                        ftthOnuTickTower(m);
                        return;
                    }
                    setStatus('Sesi tidak aktif', 'off');
                    var txv = document.getElementById('ftthOnuTxVal');
                    var rxv = document.getElementById('ftthOnuRxVal');
                    if (txv) txv.textContent = '-';
                    if (rxv) rxv.textContent = '-';
                    return;
                }
                setStatus('Live', 'live');
                /* session_history dari server: selisih antar sampel counter,
                   chart terisi penuh pada tick pertama */
                var pair = ftthHistRates(Array.isArray(d.session_history) ? d.session_history : []);
                var lastRx = pair[0].length ? pair[0][pair[0].length - 1] : null;
                var lastTx = pair[1].length ? pair[1][pair[1].length - 1] : null;
                var txVal = document.getElementById('ftthOnuTxVal');
                var rxVal = document.getElementById('ftthOnuRxVal');
                if (txVal) txVal.textContent = lastTx === null ? '-' : ftthHumanRate(lastTx);
                if (rxVal) rxVal.textContent = lastRx === null ? '-' : ftthHumanRate(lastRx);
                ftthOnuChartFill(pair[0].slice(-40), pair[1].slice(-40));
            }).catch(function() {
                setStatus('Gagal memuat traffic', 'off');
            });
        }

        /* Trafik AGREGAT TOWER HOTSPOT: total bandwidth interface MikroTik yang
           melayani hotspot (bukan per-user). Menampilkan Live walau tak ada
           sesi login aktif. */
        function ftthOnuTickTower(m) {
            var statusEl = document.getElementById('ftthOnuTrafficStatus');
            var setTower = function(txt, cls, count) {
                if (!statusEl) return;
                statusEl.className = 'ftth-onu-traffic-status' + (cls ? ' ' + cls : '') + ' ftth-status-row';
                statusEl.innerHTML = (txt || '') + '<span class="ftth-onu-clients-badge" title="Jumlah client yang sedang aktif"><i class="fa-solid fa-users"></i> <b>' + (count != null ? count : 0) + '</b></span>';
            };
            var nowMs = Date.now();
            if (ftthTowerTickAt && nowMs - ftthTowerTickAt < 3000) return;
            ftthTowerTickAt = nowMs;
            /* customer_id card ini -> filter client hotspot ke ONU-nya sendiri */
            var custId = (m && m.customer_id) ? m.customer_id : (m && m.source === 'customer' ? m.id : null);
            var url = '/noc/features/map/hotspot-tower-traffic' + (custId ? ('?customer_id=' + encodeURIComponent(custId)) : '');
            mtApi(url, 'GET').then(function(r) {
                var d = r.data || {};
                if (!d.ok || d.online === false) {
                    var txv = document.getElementById('ftthOnuTxVal');
                    var rxv = document.getElementById('ftthOnuRxVal');
                    if (txv) txv.textContent = '-';
                    if (rxv) rxv.textContent = '-';
                    setTower('Tower tidak Terhubung', 'off', 0);
                    var hsBox = document.getElementById('ftthOnuHsClients');
                    if (hsBox) { hsBox.hidden = true; }
                    return;
                }
                /* Bila beberapa tower berbagi 1 server hotspot, data trafik &
                   user aktif bersifat AGREGAT/GLOBAL — labelkan jelas agar tidak
                   terlihat sebagai trafik per-tower. */
                var towerLabel = (d.shared && d.shared === true)
                    ? 'Live (Global · Server: ' + (d.server || 'hotspot') + ')'
                    : 'Live (Agregat Tower)';
                setTower(towerLabel, 'live', d.clients != null ? d.clients : 0);
                var down = d.down != null ? parseFloat(d.down) : null;
                var up = d.up != null ? parseFloat(d.up) : null;
                var txVal = document.getElementById('ftthOnuTxVal');
                var rxVal = document.getElementById('ftthOnuRxVal');
                if (txVal) txVal.textContent = up != null ? ftthHumanRate(up) : '-';
                if (rxVal) rxVal.textContent = down != null ? ftthHumanRate(down) : '-';
                /* Riwayat counter interface tower di server → laju instan */
                var pair = ftthHistRates(Array.isArray(d.history) ? d.history : []);
                ftthOnuChartFill(pair[0].slice(-40), pair[1].slice(-40));
                ftthRenderHsClients([], false, d.clients != null ? d.clients : 0, d.server || null);
            }).catch(function() {
                setTower('Tower tidak Terhubung', 'off', 0);
                var hsBox = document.getElementById('ftthOnuHsClients');
                if (hsBox) { hsBox.hidden = true; }
            });
        }

        /* Tampilkan pemetaan client hotspot aktif -> ONU (tanpa menampilkan
           username login; hanya ONU/pelanggan, IP & MAC). */
        function ftthRenderHsClients(list, truncated, total, server) {
            var box = document.getElementById('ftthOnuHsClients');
            if (!box) return;
            /* Hanya tampilkan info server (global) — tidak per client (IP/MAC),
               agar card tidak membanjir walau ada ribuan client aktif. */
            box.hidden = false;
            var label = server ? ('Server: ' + server) : 'Hotspot (Global)';
            var meta = (typeof total === 'number' && total > 0) ? (total + ' client aktif') : '';
            box.innerHTML = '<div class="ftth-onu-hs-client"><span class="hs-dot"></span><span class="hs-onu">' + escapeHtml(label) + '</span>' + (meta ? '<span class="hs-meta">' + escapeHtml(meta) + '</span>' : '') + '</div>';
        }

        function ftthOnuStartTraffic() {
            ftthOnuStopTraffic();
            if (typeof Chart === 'undefined') {
                var loadChart = function(src, fallback) {
                    var s = document.createElement('script');
                    s.src = src;
                    s.onload = function() { ftthOnuInitChart(); ftthOnuTick(); };
                    s.onerror = function() { if (fallback) fallback(); };
                    document.head.appendChild(s);
                };
                loadChart('/vendor/chart.umd.js', function() {
                    loadChart('https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js');
                });
            } else {
                ftthOnuInitChart();
                ftthOnuTick();
            }
            ftthOnuTrafficTimer = setInterval(ftthOnuTick, 2000);
        }
        function ftthOnuStopTraffic() {
            if (ftthOnuTrafficTimer) { clearInterval(ftthOnuTrafficTimer); ftthOnuTrafficTimer = null; }
        }

        function ftthCustDetailRefresh() {
            var m = ftthDetailData;
            if (!ftthResolveCid(m)) return;
            ftthClearLog();
            ftthCustLoad();
        }

        function ftthCustPing() {
            var m = ftthDetailData;
            var cid = ftthResolveCid(m);
            var ip = (m && (m.ip_address || (m.attributes && m.attributes.ip))) || null;
            var ipEl = document.getElementById('ftthOnuIp');
            if (ipEl && ipEl.textContent && ipEl.textContent.trim() !== '' && ipEl.textContent.trim() !== '-') {
                ip = ipEl.textContent.trim();
            }
            ftthClearLog();
            var doPing = function(payload, hostLabel) {
                ftthLogRow('info', (ftthT('log.ping') || 'Ping') + (hostLabel ? ' ' + hostLabel : '') + ' …');
                mtApi('/noc/features/map/customer/ping', 'POST', payload).then(function(r) {
                    if (r.data && r.data.ok) {
                        var res = r.data.result || {};
                        var line = ftthT('result.host') + ' ' + (r.data.host || '-') + '\n' +
                            ftthT('result.status') + ' ' + res.status + '\n' +
                            ftthT('result.latency') + ' ' + (res.latency_ms !== null && res.latency_ms !== undefined ? res.latency_ms + ' ms' : '-') + '\n' +
                            ftthT('result.jitter') + ' ' + (res.jitter_ms !== null && res.jitter_ms !== undefined ? res.jitter_ms + ' ms' : '-') + '\n' +
                            ftthT('result.pkt_loss') + ' ' + res.packet_loss_percent + ' %';
                        ftthLogRow(res.status === 'online' ? 'ok' : (res.status === 'warning' ? 'info' : 'err'), line);
                    } else {
                        ftthLogRow('err', (r.data && r.data.error) || ftthT('log.gagal_ping'));
                    }
                }).catch(function() {
                    ftthLogRow('err', ftthT('log.gagal_ping_net'));
                });
            };
            if (cid) {
                doPing({ id: cid });
                return;
            }
            if (ip) {
                doPing({ ip: ip }, ip);
                return;
            }
            ftthLogRow('err', ftthT('log.gagal_ping') || 'Tidak ada IP untuk di-ping');
        }

        function ftthCustAcs() {
            var m = ftthDetailData;
            var cid = ftthResolveCid(m);
            if (!cid) return;
            var onu = (ftthCustDetail && ftthCustDetail.onu) || {};
            if (!onu.acs_device_id) {
                ftthLogRow('err', ftthT('log.no_acs_device'));
                return;
            }
            ftthClearLog();
            ftthLogRow('info', ftthT('log.loading_acs') + ' ' + onu.acs_device_id + ' …');
            mtApi('/noc/features/map/customer/acs', 'POST', { id: cid }).then(function(r) {
                if (r.data && r.data.ok) {
                    var a = r.data.acs || {};
                    var line = ftthT('result.device') + ' ' + (r.data.device_id || '-') + '\n' +
                        ftthT('result.pabrikan') + ' ' + (a.manufacturer || '-') + '\n' +
                        ftthT('result.produk') + ' ' + (a.product_class || '-') + '\n' +
                        ftthT('result.firmware') + ' ' + (a.software_version || '-') + '\n' +
                        ftthT('cust.ip_publik') + ' ' + (a.external_ip || '-') + '\n' +
                        ftthT('result.mode') + ' ' + (a.mode || '-');
                    ftthLogRow('ok', line);
                } else {
                    ftthLogRow('err', (r.data && r.data.error) || ftthT('log.gagal_ambil_acs'));
                }
            }).catch(function() {
                ftthLogRow('err', ftthT('log.gagal_ambil_acs_net'));
            });
        }

        function ftthCustWifi() {
            var m = ftthDetailData;
            var cid = ftthResolveCid(m);
            if (!cid) return;
            ftthClearLog();
            ftthLogRow('info', ftthT('log.mengambil_ssid'));
            mtApi('/noc/features/map/customer/acs', 'POST', { id: cid }).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthAcsInfo = r.data.acs || {};
                    var a = ftthAcsInfo;
                    var wifiOn = String(a.wifi_enabled) === '1' || a.wifi_enabled === true || String(a.wifi_enabled).toLowerCase() === 'true';
                    var line = 'SSID: ' + (a.ssid || '-') + '\n' +
                        'WiFi: ' + (wifiOn ? ftthT('cust.aktif') : ftthT('cust.mati')) + '\n' +
                        ftthT('result.channel') + ' ' + (a.channel || '-') + '\n' +
                        ftthT('result.mode') + ' ' + (a.mode || '-');
                    ftthLogRow('ok', line);
                    ftthCustRender(m, ftthCustDetail);
                } else {
                    ftthLogRow('err', (r.data && r.data.error) || ftthT('log.gagal_ambil_ssid'));
                }
            }).catch(function() {
                ftthLogRow('err', ftthT('log.gagal_ambil_ssid_net'));
            });
        }

        function ftthCustReboot() {
            var m = ftthDetailData;
            if (!ftthResolveCid(m)) return;
            var onu = (ftthCustDetail && ftthCustDetail.onu) || {};
            if (!onu.onu_id) {
                ftthLogRow('err', ftthT('log.no_onu_reboot'));
                return;
            }
            if (!confirm(ftthT('confirm.reboot_onu') + ' ' + (onu.onu_id || '') + '?')) return;
            ftthClearLog();
            ftthLogRow('info', ftthT('log.reboot_sending'));
            mtApi('/noc/features/map/onu/reboot', 'POST', { onu_id: onu.id }).then(function(r) {
                if (r.data && r.data.ok) {
                    ftthLogRow('ok', r.data.message || ftthT('log.reboot_sent'));
                } else {
                    ftthLogRow('err', (r.data && r.data.error) || ftthT('log.gagal_reboot'));
                }
            }).catch(function() {
                ftthLogRow('err', ftthT('log.gagal_reboot_net'));
            });
        }

        /* ── Marker peta: OLT / Router / ODC / Perangkat / Customer ── */

        var markerLayer = null;
        var cableLayer = null;
        var ftthSpotMarkers = {};
        var cableEdit = { active: false, m: null, from: null, to: null, pts: [], layers: [], ghost: null, color: '#38bdf8', width: 3, curve: false };
        var markersCache = [];
        var ftthMarkerById = {};
        /* Fokuskan marker perangkat di peta (dipakai card Perangkat dsb.) */
        window.ftthFocusMarker = function(id) {
            var mk = ftthMarkerById[id];
            if (mk) mk.fire('click');
        };

        var ftthHotspotNames = {};

        function ftthLoadHotspotNames() {
            mtApi('/noc/features/map/hotspot', 'GET').then(function(r) {
                if (r.data && r.data.ok) {
                    (r.data.clients || []).forEach(function(c) {
                        if (c.name) ftthHotspotNames[String(c.name).toLowerCase()] = true;
                    });
                }
                if (markersCache.length) renderMapMarkers();
            }).catch(function() {});
        }

        var deviceIcons = {
            'OLT': 'fa-server',
            'ODC': 'fa-boxes-stacked',
            'OTB': 'fa-box-archive',
            'ODP': 'fa-code-branch',
            'HTB': 'fa-diagram-project',
            'CLOSURE': 'fa-link',
            'ONU': 'fa-wifi',
            'CUSTOMER': 'fa-wifi',
            'CUSTOM': 'fa-microchip',
            'ROUTER': 'fa-tower-cell'
        };

        function ftthDeviceIcon(m, color) {
            var t = String(m.type).toUpperCase();
            var icon = deviceIcons[t] || 'fa-microchip';
            if (t === 'CUSTOMER' || t === 'ONU') {
                var isHs = false;
                var cType = (m.onu_type || m.customer_type || m.detail || '').toString().toLowerCase();
                if (cType === 'hotspot') isHs = true;
                if (!isHs && m.attributes && m.attributes.hotspot) isHs = true;
                if (!isHs) {
                    var hsName = String(m.label || m.name || '').toLowerCase();
                    if (hsName && ftthHotspotNames[hsName]) isHs = true;
                }
                icon = isHs ? 'fa-tower-cell' : 'fa-wifi';
            }
            var status = m.status;
            var blink = (status === 'online') ? ' blink-on' : (status === 'offline') ? ' blink-off' : '';
            var glowMap = { 'OLT': ' ftth-glow-olt', 'ODC': ' ftth-glow-odc', 'ODP': ' ftth-glow-odp', 'CUSTOMER': ' ftth-glow-customer' };
            var glow = glowMap[t] || '';
            var iconColor = (blink || glow) ? '' : (color || '');
            var styleAttr = iconColor ? ' style="color:' + iconColor + '"' : '';
            return L.divIcon({
                className: 'ftth-marker-label',
                html: '<div class="ftth-ic' + blink + '">' +
                    '<div class="ftth-ic-i"><i class="fa-solid ' + icon + '' + glow + '"' + styleAttr + '></i></div>' +
                    '</div>',
                iconSize: [26, 26],
                iconAnchor: [13, 13]
            });
        }

        var ftthMapLoadCount = 0;
        function ftthMapLoadStart() {
            ftthMapLoadCount++;
            var el = document.getElementById('ftthMapLoader');
            if (el) el.hidden = false;
        }
        function ftthMapLoadEnd() {
            ftthMapLoadCount = Math.max(0, ftthMapLoadCount - 1);
            if (ftthMapLoadCount === 0) {
                var el = document.getElementById('ftthMapLoader');
                if (el) el.hidden = true;
            }
        }
        var ftthMarkerRefreshTimer = null;
        function ftthRefreshMarkers() {
            /* Don't disrupt cable editing/repositioning */
            if (typeof cableEdit !== 'undefined' && cableEdit.active) return;
            if (typeof cableRepos !== 'undefined' && cableRepos.active) return;
            if (!markerLayer) return;
            mtApi('/noc/features/map/markers', 'GET').then(function(r) {
                if (r.data && r.data.ok && r.data.markers) {
                    markersCache = r.data.markers;
                    renderMapMarkers();
                }
            }).catch(function() {});
        }
        function startMarkerAutoRefresh() {
            if (ftthMarkerRefreshTimer) return;
            ftthMarkerRefreshTimer = setInterval(ftthRefreshMarkers, 10000);
        }

        function loadMapMarkers() {
            /* Stale-while-revalidate: render cache localStorage dulu agar peta
               langsung tampil tanpa loader, data segar diambil di belakang */
            var cached = null;
            try {
                var raw = localStorage.getItem('ftthMarkersCache');
                if (raw) {
                    var c = JSON.parse(raw);
                    if (c && Array.isArray(c.markers) && c.markers.length && c.ts && (Date.now() - c.ts) < 600000) cached = c.markers;
                }
            } catch (e) { cached = null; }
            if (cached && !markersCache.length) {
                markersCache = cached;
                renderMapMarkers();
                ftthMapLoadEnd();
                startMarkerAutoRefresh();
            } else {
                ftthMapLoadStart();
            }
            if (!markerLayer) markerLayer = L.layerGroup().addTo(map);
            if (!cableLayer) cableLayer = L.layerGroup().addTo(map);
            mtApi('/noc/features/map/markers', 'GET').then(function(r) {
                markersCache = (r.data && r.data.ok && r.data.markers) ? r.data.markers : [];
                try { localStorage.setItem('ftthMarkersCache', JSON.stringify({ ts: Date.now(), markers: markersCache })); } catch (e) {}
                renderMapMarkers();
                ftthMapLoadEnd();
                startMarkerAutoRefresh();
            }).catch(function() { markersCache = []; ftthMapLoadEnd(); });
        }

        function ftthIsOnuMarker(m) {
            if (!m) return false;
            var t = String(m.type || '').toUpperCase();
            return t === 'ONU' || t === 'CUSTOMER' || !!m.onu_type || !!m.customer_id || (m.customer_type && m.customer_type !== 'hotspot') || m.customer_type === 'ppp' || m.customer_type === 'wireless';
        }

        function ftthVisCategory(m) {
            var t = String(m.type || '').toUpperCase();
            if (m.source === 'customer' || (m.source === 'device' && t === 'ONU')) {
                var active = (m.onu_status === 'online') || (m.source === 'device' && m.status === 'online');
                return active ? 'onuOnline' : 'onuOffline';
            }
            if (m.source === 'olt' || m.source === 'router') return 'router';
            if (m.source === 'odc' || t === 'ODC') {
                return (/ODC-ALK-UTAMA/i.test(m.label || '')) ? 'odc' : 'other';
            }
            if (t === 'ROUTER' || t === 'OLT') return 'router';
            if (t === 'ODP') return 'odp';
            if (t === 'OTB') return 'otb';
            if (t === 'CLOSURE' || t === 'HTB') return 'closure';
            return 'other';
        }

        function ftthSpotKey(type, label) {
            return (String(type).toUpperCase() + ' — ' + label).replace(/\s+/g, ' ').trim().toUpperCase();
        }

        function ftthSpotFromString(parent) {
            return String(parent).replace(/\s+/g, ' ').trim().toUpperCase();
        }

        function ftthClearMeteors() {
            document.querySelectorAll('g[data-ftth-meteor]').forEach(function(n) { n.remove(); });
        }

        /* Meteor menyala berjalan di sepanjang kabel menuju perangkat (SVG animateMotion) */
        function ftthAddMeteors(pl, mode, mColor) {
            var el = pl.getElement ? pl.getElement() : null;
            if (!el || !el.ownerSVGElement) return;
            mColor = ftthCableNormColor(mColor || '#38bdf8');
            var svg = el.ownerSVGElement;
            var pid = 'ftth-cable-path-' + pl._cableMarkerId;
            el.setAttribute('id', pid);
            var NS = 'http://www.w3.org/2000/svg';
            var count = mode === 'glow-fast' ? 8 : 1;
            var dur = mode === 'glow-fast' ? 2.2 : 1.3;
            for (var i = 0; i < count; i++) {
                var g = document.createElementNS(NS, 'g');
                g.setAttribute('data-ftth-meteor', pl._cableMarkerId);
                var tail = document.createElementNS(NS, 'circle');
                tail.setAttribute('r', mode === 'glow-fast' ? '4.5' : '6');
                tail.setAttribute('fill', ftthHexToRgba(mColor, 0.30));
                g.appendChild(tail);
                var head = document.createElementNS(NS, 'circle');
                head.setAttribute('r', mode === 'glow-fast' ? '2.4' : '3');
                head.setAttribute('fill', '#ffffff');
                head.setAttribute('stroke', mColor);
                head.setAttribute('stroke-width', '1.4');
                g.appendChild(head);
                var am = document.createElementNS(NS, 'animateMotion');
                am.setAttribute('dur', dur + 's');
                am.setAttribute('repeatCount', 'indefinite');
                am.setAttribute('begin', (-(i * dur / count)).toFixed(2) + 's');
                var mp = document.createElementNS(NS, 'mpath');
                mp.setAttribute('href', '#' + pid);
                mp.setAttributeNS('http://www.w3.org/1999/xlink', 'xlink:href', '#' + pid);
                am.appendChild(mp);
                g.appendChild(am);
                svg.appendChild(g);
            }
        }

        /* Meteor disembunyikan saat zoom (mencegah flicker membesar), dibuat ulang setelah selesai */
        map.on('zoomstart', function() {
            document.querySelectorAll('g[data-ftth-meteor]').forEach(function(n) { n.style.display = 'none'; });
        });
        map.on('zoomend', function() {
            ftthClearMeteors();
            if (!cableLayer) return;
            cableLayer.getLayers().forEach(function(pl) {
                var an = pl.options._cableAnim;
                if ((an === 'glow-fast' || an === 'glow-slow') && pl.options._cableOnline) {
                    ftthAddMeteors(pl, an, pl.options._cableMeteor);
                }
            });
        });

        /* ── Toggle Glow Kabel (di card Edit Kabel): kabel memancarkan cahaya sesuai warnanya ── */
        var ftthGlowOn = false;
        function ftthApplyCableGlow() {
            var pane = map.getPane('overlayPane');
            if (pane) pane.classList.toggle('ftth-cables-glow', ftthGlowOn);
            var cb = document.getElementById('fcpGlow');
            if (cb) cb.checked = ftthGlowOn;
        }
        window.ftthToggleCableGlow = function() {
            ftthGlowOn = !ftthGlowOn;
            try { localStorage.setItem('ftth_glow', ftthGlowOn ? '1' : '0'); } catch (e) {}
            ftthApplyCableGlow();
        };
        (function() {
            try { ftthGlowOn = localStorage.getItem('ftth_glow') === '1'; } catch (e) {}
            ftthApplyCableGlow();
        })();

        function ftthNodeOnline(m) {
            var t = String(m.type || '').toUpperCase();
            if (t !== 'ONU' && m.source !== 'customer') return false;
            return (m.device_status === 'online') || (m.status === 'online') || (m.onu_status === 'online');
        }

        function ftthDrawCables(spots, spotMarkers) {
            /* Build device tree: child spotKey -> parent spotKey + children lists */
            var parentOf = {}, childrenOf = {}, markerOf = {};
            markersCache.forEach(function(m) {
                if (!m.parent) return;
                var key = ftthSpotKey(m.type, m.label);
                var pkey = ftthSpotFromString(m.parent);
                parentOf[key] = pkey;
                markerOf[key] = m;
                (childrenOf[pkey] = childrenOf[pkey] || []).push(key);
            });

            /* A node is "active" if it is an online ONU, or has any active descendant.
               This drives cable animation: a cable animates iff its child node is active. */
            var activeMemo = {};
            function isActive(key, stack) {
                if (activeMemo.hasOwnProperty(key)) return activeMemo[key];
                if (stack.indexOf(key) !== -1) { activeMemo[key] = false; return false; }
                stack = stack.concat([key]);
                var m = markerOf[key];
                var self = m ? ftthNodeOnline(m) : false;
                var kids = childrenOf[key] || [];
                var childActive = false;
                for (var i = 0; i < kids.length; i++) {
                    if (isActive(kids[i], stack)) { childActive = true; break; }
                }
                var res = self || childActive;
                activeMemo[key] = res;
                return res;
            }

            markersCache.forEach(function(m) {
                if (!VIS.cable || !m.parent) return;
                var cat = ftthVisCategory(m);
                if (cat !== 'other' && !VIS[cat]) return;
                var parentKey = ftthSpotFromString(m.parent);
                var pm = spotMarkers[parentKey];
                if (pm) {
                    var pCat = ftthVisCategory(pm);
                    if (pCat !== 'other' && !VIS[pCat]) return;
                }
                var from = spots[parentKey];
                if (!from) return;
                var to = [m.lat, m.lon];
                if (Math.abs(from[0] - to[0]) < 1e-7 && Math.abs(from[1] - to[1]) < 1e-7) return;
                var attrs = (m.attributes && typeof m.attributes === 'object') ? m.attributes : {};
                var color = attrs.cable_color || attrs.warna_core;
                if (!color) {
                    var pAttrs = (pm && pm.attributes && typeof pm.attributes === 'object') ? pm.attributes : {};
                    color = pAttrs.cable_color || pAttrs.warna_core;
                }
                if (!color) color = (String(m.type || '').toUpperCase() === 'ONU') ? '#3b82f6' : ftthDeviceColor(m.type);
                var childKey = ftthSpotKey(m.type, m.label);
                var online = isActive(childKey, []);
                var width = Number(attrs.cable_width) || 0;
                if (!width) width = online ? 2.5 : 2;
                var cls = 'ftth-cable ' + ftthCableAnimClass(attrs.cable_anim || '', online);
                var path = (attrs.cable_path && Array.isArray(attrs.cable_path) && attrs.cable_path.length >= 2)
                    ? attrs.cable_path.map(function(p) { return [Number(p[0]), Number(p[1])]; })
                    : [from, to];
                if (attrs.cable_curve && path.length >= 3) path = ftthCatmullRom(path, 12);
                var pl = L.polyline(path, {
                    className: cls,
                    color: color,
                    weight: width,
                    opacity: online ? 0.9 : 0.55
                }).addTo(cableLayer);
                pl._cableMarkerId = m.id;
                pl.options._cableOnline = online;
                pl.options._cableAnim = attrs.cable_anim || '';
                pl.options._cableMeteor = attrs.cable_meteor_color || '';
                var gel = pl.getElement ? pl.getElement() : null;
                if (gel) gel.style.setProperty('--glowc', color);
                if (online && (attrs.cable_anim === 'glow-fast' || attrs.cable_anim === 'glow-slow')) {
                    ftthAddMeteors(pl, attrs.cable_anim, attrs.cable_meteor_color);
                }
            });
        }

        function renderMapMarkers() {
            if (!markerLayer) markerLayer = L.layerGroup().addTo(map);
            if (!cableLayer) cableLayer = L.layerGroup().addTo(map);
            markerLayer.clearLayers();
            cableLayer.clearLayers();
            ftthMarkerById = {};
            ftthClearMeteors();
            if (!markersCache.length) return;

            var spotMarkers = {};
            markersCache.forEach(function(m) {
                spotMarkers[ftthSpotKey(m.type, m.label)] = m;
            });
            ftthSpotMarkers = spotMarkers;
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
                if (VIS.onuText !== 'sembunyi') {
                    var otUp = String(m.type || '').toUpperCase();
                    /* ONU di peta berupa device bertipe ONU / punya link pelanggan;
                       marker source=customer sudah tidak ada lagi (semua dari devices) */
                    var isOnuMarker = m.source === 'customer' || otUp === 'ONU' || !!m.customer_id;
                    if (isOnuMarker) {
                        var oAttrs = (m.attributes && typeof m.attributes === 'object') ? m.attributes : {};
                        if (VIS.onuText === 'nama') onuText = m.name || m.label || '';
                        else if (VIS.onuText === 'pppoe') onuText = m.pppoe_username || oAttrs.pppoe_user || '';
                    }
                }
                if (isNet) {
                    mk = L.marker([m.lat, m.lon], { icon: ftthDeviceIcon(m, color) });
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
                ftthMarkerById[m.id] = mk;
                mk.on('click', function() {
                    if (cableEdit.active) return;
                    /* Mode OTDR: klik perangkat = pilih titik ukur, bukan buka detail */
                    if (measure.mode === 'otdr' && measure.active && !measure.finished && typeof ftthOtdrPickDevice === 'function') {
                        L.DomEvent.stop.apply(null, arguments);
                        ftthOtdrPickDevice(m);

                        return;
                    }
                    if (ftthActiveIconEl) ftthActiveIconEl.classList.remove('ftth-marker-active');
                    ftthActiveMarker = mk;
                    var el = mk.getElement();
                    if (el) {
                        var ic = el.querySelector('.ftth-ic');
                        if (ic) { ic.classList.add('ftth-marker-active'); ftthActiveIconEl = ic; }
                    }
                    if (m.source === 'customer' || ftthIsOnuMarker(m)) ftthShowCustomer(m);
                    else ftthShowDetail(m);
                });
            });
        }

        loadMapMarkers();
        loadDevices();
        ftthLoadHotspotNames();

        window.ftthOpenAddDevice = function() { ftthOpenAddDevice(); };
        window.ftthCloseAddDevice = function() { ftthCloseAddDevice(); };
        window.ftthRenderDeviceFields = function() { ftthRenderDeviceFields(); };
        window.ftthTogglePppoeField = function() { ftthTogglePppoeField(); };
        window.ftthSaveDevice = function() { ftthSaveDevice(); };
        window.ftthOpenDevices = function() { ftthOpenDevices(); };
        window.ftthCloseDevices = function() { ftthCloseDevices(); };
        window.ftthBrowseCategory = function(type) { ftthBrowseCategory(type); };
        window.ftthDevicesShowOverview = function() { ftthDevicesShowOverview(); };
        window.ftthBrowseFilter = function() { ftthBrowseFilter(); };
        window.ftthBrowseDeleteAll = function() { ftthBrowseDeleteAll(); };
        window.ftthBrowseFlyDevice = function(id) { ftthBrowseFlyDevice(id); };
        window.ftthBrowseFlyCustomer = function(id) { ftthBrowseFlyCustomer(id); };
        window.ftthBrowseEditDevice = function(id) { ftthBrowseEditDevice(id); };
        window.ftthBrowseEditCustomer = function(id) { ftthBrowseEditCustomer(id); };
        window.ftthBrowseDeleteDevice = function(id) { ftthBrowseDeleteDevice(id); };
        window.ftthBrowseDeleteCustomer = function(id) { ftthBrowseDeleteCustomer(id); };
        window.ftthDeleteDevice = function(id) { ftthDeleteDevice(id); };
        window.ftthToggleStatus = function(id, cur) { ftthToggleStatus(id, cur); };
        window.ftthEditDeviceFromList = function(idx) { ftthEditDeviceFromList(idx); };
        window.ftthDeviceDetailFromList = function(idx) { ftthDeviceDetailFromList(idx); };
        window.ftthOpenOnuTable = function() { ftthOpenOnuTable(); };
        window.ftthCloseOnuTable = function() { ftthCloseOnuTable(); };
        window.ftthOnuTableFilter = function() { ftthOnuTableFilter(); };
        window.ftthOnuPageGo = function(dir) { ftthOnuPageGo(dir); };
        window.ftthOnuTablePrint = function(type) { ftthOnuTablePrint(type); };
        window.ftthOnuTableExport = function(type) { ftthOnuTableExport(type); };
        window.ftthCloseDetail = function() { ftthCloseDetail(); };
        window.ftthEditFromDetail = function() { ftthEditFromDetail(); };
        window.ftthDetailToggleStatus = function() { ftthDetailToggleStatus(); };
        window.ftthDetailDelete = function() { ftthDetailDelete(); };
        window.ftthOdcAction = function(a) { ftthOdcAction(a); };
        window.ftthCopyText = function(t, l) { ftthCopyText(t, l); };
        window.ftthCustPing = function() { ftthCustPing(); };
        window.ftthCustDetailRefresh = function() { ftthCustDetailRefresh(); };
        window.ftthCustAcs = function() { ftthCustAcs(); };
        window.ftthCustWifi = function() { ftthCustWifi(); };
        window.ftthCustReboot = function() { ftthCustReboot(); };

        /* ── Auto-sync saat panel dibuka (Mikrotik + OLT + GenieACS) ── */

        var autoSyncBusy = false;

        function setToolbarSyncing(feature, syncing) {
            var btn = document.querySelector('.ftth-btn[data-feature="' + feature + '"]');
            if (!btn) return;
            if (syncing && !btn.dataset.titleOrig) btn.dataset.titleOrig = btn.getAttribute('title') || '';
            btn.classList.toggle('ftth-syncing', syncing);
            btn.setAttribute('title', syncing ? ftthT('btn.syncing') : (btn.dataset.titleOrig || ''));
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

            var genie = mtApi('/noc/features/map/genieacs/sync', 'POST').then(function(r) {
                var d = (r.data && r.data.ok) ? r.data : null;
                if (d) genieacsSummaryCache = d;
                if (!document.getElementById('ftthGenieacsBackdrop').hidden && !genieacsSummaryCache) renderGenieacsSummary(d);
                return d;
            }).catch(function() {
                if (!document.getElementById('ftthGenieacsBackdrop').hidden && !genieacsSummaryCache) renderGenieacsSummary(null);
                return null;
            });

            Promise.race([
                Promise.all([mikrotik, olt, genie]),
                new Promise(function(resolve) { setTimeout(resolve, 2500); })
            ]).then(function(results) {
                setToolbarSyncing('sync-mikrotik', false);
                setToolbarSyncing('sync-olt', false);
                setToolbarSyncing('sync-genieacs', false);
                autoSyncBusy = false;

                /* Segarkan marker begitu sync beres agar status peta ikut terbaru */
                if (results && typeof ftthRefreshMarkers === 'function') ftthRefreshMarkers();

                if (!results) {
                    ftthToast(ftthT('toast.auto_sync_bg'), 'ok');
                    return;
                }

                var parts = [];
                if (results[0]) parts.push('Mikrotik ' + results[0].ok + '/' + results[0].total);
                if (results[1]) parts.push('OLT ' + results[1].ok + '/' + results[1].total);
                if (results[2]) parts.push('GenieACS ' + (results[2].online || 0) + ' online');
                ftthToast(ftthT('toast.auto_sync_done') + ' ' + (parts.length ? parts.join(', ') : ftthT('toast.no_sync_data')), 'ok');
            });
        };

        /* Auto-sync langsung barengan load data peta (paralel, tanpa menunggu) */
        ftthAutoSync();

        /* Pre-warm grafik trafik WAN Mikrotik & PON OLT: sampling mulai sekarang
           di latar sehingga saat card Sync dibuka grafik langsung bergerak */
        ftthMtWanStart(false);
        ftthOltPonStart(false);

        /* Prime cache card Sync (router/OLT/GenieACS) supaya membuka card
           pertama kali juga tanpa loader — data diambil sekali dari DB */
        setTimeout(function() {
            mtApi('/noc/features/map/mikrotik', 'GET').then(function(r) { renderRouterList(r.data.routers || []); }).catch(function() {});
            mtApi('/noc/features/map/olt', 'GET').then(function(r) { renderOltList(r.data.olts || []); }).catch(function() {});
            mtApi('/noc/features/map/genieacs', 'GET').then(function(r) {
                genieacsConfigCache = r.data || {};
                var input = document.getElementById('genieacsUrl');
                if (input && r.data.base_url) input.value = r.data.base_url;
            }).catch(function() {});
        }, 5000);

        /* Preload ONU table data in background so first open is instant */
        setTimeout(function() { mtApi('/noc/features/map/onu-table', 'GET'); }, 500);
        /* Preload users data */
        setTimeout(function() {
            mtApi('/noc/features/map/users', 'GET').then(function(r) {
                ftthUsersData = r.data.users || [];
            });
        }, 1000);

        /* Apply saved language on load */
        document.getElementById('ftthLangLabel').textContent = FTTH_LANG === 'id' ? 'EN' : 'ID';
        ftthApplyLang();

        /* Akses read-only state peta untuk QA/otomasi (tidak dipakai logika UI) */
        window.ftthMapApi = {
            map: function() { return map; },
            markersCache: function() { return markersCache; },
            cableLayer: function() { return cableLayer; },
            glowOn: function() { return ftthGlowOn; },
            preZoom: function() { return ftthPreZoom; },
            wanTimer: function() { return ftthMtWanTimer; },
            wanPts: function() { return ftthMtWanRxPts.length; },
            wanTicks: function() { return ftthMtWanTickCount; },
            genieRender: function(d) { return renderGenieacsSummary(d); },
            custRender: function(m, d) { return ftthCustRender(m, d); },
            oltPonTimer: function() { return ftthOltPonTimer; },
            oltPonPts: function() { return ftthOltPonRxPts.length; }
        };
    })();
    </script>
</body>
</html>
