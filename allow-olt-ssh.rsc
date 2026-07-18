# ============================================================
# MikroTik RSC — Allow SSH access to C-Data OLT (172.10.10.2)
# ============================================================
# Import via WinBox/terminal: /import file-name=allow-olt-ssh.rsc
#
# Network:
#   Server (Win)  : 172.10.0.3  (Ethernet 4)
#   MikroTik      : 172.10.0.1  (management) + 172.10.10.x (OLT subnet)
#   C-Data OLT    : 172.10.10.2
#
# Catatan: Route ke 172.10.10.0/24 sudah directly connected
#          (MikroTik punya interface di subnet OLT).
#          Yang perlu dipastikan: firewall allow SSH antar subnet.
# ============================================================

# ── 1. Pastikan route ke OLT ada (safety net) ──
:if ([:len [/ip route find dst-address=172.10.10.0/24]] > 0) do={
    :log info "[OLT-SSH] Route 172.10.10.0/24 sudah ada — OK"
} else={
    /ip route add dst-address=172.10.10.0/24 \
        gateway=[/interface get [find where name~"172.10.10" || where comment~"olt" || where comment~"OLT"] name] \
        distance=1 \
        comment="Route to C-Data OLT subnet"
    :log info "[OLT-SSH] Route 172.10.10.0/24 ditambahkan"
}

# ── 2. Firewall: Allow SSH (TCP 22) dari management ke OLT ──
:if ([:len [/ip firewall filter find chain=forward src-address=172.10.0.0/24 dst-address=172.10.10.0/24 protocol=tcp dst-port=22 comment~"allow-olt-ssh"]] > 0) do={
    :log info "[OLT-SSH] Firewall rule sudah ada — OK"
} else={
    /ip firewall filter add \
        chain=forward \
        src-address=172.10.0.0/24 \
        dst-address=172.10.10.0/24 \
        protocol=tcp dst-port=22 \
        action=accept \
        place-before=[find chain=forward action=drop] \
        comment="allow-olt-ssh: server → OLT TCP/22"
    :log info "[OLT-SSH] Firewall rule ditambahkan: allow TCP/22 server → OLT"
}

# ── 3. Firewall: Allow reply (OLT → server) ──
:if ([:len [/ip firewall filter find chain=forward src-address=172.10.10.0/24 dst-address=172.10.0.0/24 protocol=tcp src-port=22 comment~"allow-olt-ssh-reply"]] > 0) do={
    :log info "[OLT-SSH] Firewall reply rule sudah ada — OK"
} else={
    /ip firewall filter add \
        chain=forward \
        src-address=172.10.10.0/24 \
        dst-address=172.10.0.0/24 \
        protocol=tcp src-port=22 \
        action=accept \
        place-before=[find chain=forward action=drop] \
        comment="allow-olt-ssh-reply: OLT → server reply TCP/22"
    :log info "[OLT-SSH] Firewall reply rule ditambahkan"
}

# ── 4. Firewall: Allow ICMP ping ke OLT (untuk monitoring) ──
:if ([:len [/ip firewall filter find chain=forward src-address=172.10.0.0/24 dst-address=172.10.10.0/24 protocol=icmp comment~"allow-olt-ping"]] > 0) do={
    :log info "[OLT-SSH] ICMP rule sudah ada — OK"
} else={
    /ip firewall filter add \
        chain=forward \
        src-address=172.10.0.0/24 \
        dst-address=172.10.10.0/24 \
        protocol=icmp \
        action=accept \
        place-before=[find chain=forward action=drop] \
        comment="allow-olt-ping: server → OLT ICMP"
    :log info "[OLT-SSH] ICMP rule ditambahkan"
}

# ── 5. Verifikasi ──
:log info "=== OLT SSH Setup Selesai — Verifikasi ==="
:put "Route:"
/ip route print where dst-address=172.10.10.0/24
:put ""
:put "Firewall rules (allow-olt):"
/ip firewall filter print where comment~"allow-olt"
:put ""
:put "Test ping ke OLT dari MikroTik:"
/ping 172.10.10.2 count=3
