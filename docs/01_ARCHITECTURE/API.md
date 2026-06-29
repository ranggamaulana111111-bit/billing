# API — RabegNet ISP Billing System

---

## API Routes

### External API (via `routes/api.php`)

| Method | Endpoint | Controller | Fungsi |
|--------|----------|------------|--------|
| GET | `/api/v1/odp/{odp}/ports` | `PortController@odpPorts` | Data realtime port ODP |
| GET | `/api/v1/odc/{odc}/ports` | `PortController@odcPorts` | Data realtime port ODC |
| POST | `/api/v1/mikrotik/hotspot-login` | `MikrotikHotspotController@hotspotLogin` | Callback voucher login dari MikroTik hotspot |

### Internal AJAX API (via `routes/web.php`)

| Method | Endpoint | Controller | Fungsi |
|--------|----------|------------|--------|
| GET | `/api/odp-routes` | `OdpruteController@routes` | Data route untuk Leaflet map |
| GET | `/api/odp-points` | `OdpruteController@points` | Data point untuk Leaflet map |
| GET | `/olts/{olt}/live` | `OltController@live` | Live data OLT |
| GET | `/mikrotik/live` | `MikrotikController@live` | Live data MikroTik |

---

## Endpoint Detail

### GET /api/v1/odp/{odp}/ports

Mengembalikan data realtime port ODP termasuk customer yang terhubung.

**Response:**
```json
{
  "odp": {
    "id": 1,
    "nama_odp": "ODP-01",
    "kapasitas_port": 8,
    "available": 3,
    "used": 5,
    "broken": 0,
    "kondisi_jalur": "UP"
  },
  "odc": {
    "id": 1,
    "nama_odc": "ODC Pusat",
    "port_odc": {
      "port_number": 3,
      "port_type": "outlet"
    }
  },
  "ports": [
    {
      "id": 1,
      "port_number": 1,
      "status": "used",
      "customer": {
        "id": 1,
        "name": "Budi",
        "phone": "08123456789",
        "package": "Home 20",
        "status": "active"
      }
    },
    {
      "id": 2,
      "port_number": 2,
      "status": "available",
      "customer": null
    }
  ],
  "timestamp": "2026-06-30T03:00:00+07:00"
}
```

### GET /api/v1/odc/{odc}/ports

Mengembalikan data realtime port ODC termasuk ODP dan jumlah customer.

**Response:**
```json
{
  "odc": {
    "id": 1,
    "nama_odc": "ODC Pusat",
    "kapasitas_port": 16,
    "odp_count": 5
  },
  "ports": [
    {
      "id": 1,
      "port_number": 1,
      "port_type": "outlet",
      "status": "used",
      "connected_odp": {
        "id": 1,
        "nama_odp": "ODP-01",
        "customer_count": 4,
        "port_used": 5,
        "port_total": 8,
        "kondisi_jalur": "UP"
      }
    },
    {
      "id": 2,
      "port_number": 2,
      "port_type": "inlet",
      "status": "available",
      "connected_odp": null
    }
  ],
  "timestamp": "2026-06-30T03:00:00+07:00"
}
```

### POST /api/v1/mikrotik/hotspot-login

Event-driven callback dari MikroTik hotspot saat user login.

**Request:**
```json
{
  "username": "RBN001",
  "password": "ABC123",
  "router_ip": "192.168.1.1",
  "mac": "AA:BB:CC:DD:EE:FF"
}
```

**Response Success:**
```json
{
  "success": true,
  "message": "Voucher valid, akses diberikan"
}
```

**Response Failed:**
```json
{
  "success": false,
  "message": "Voucher tidak ditemukan atau sudah digunakan"
}
```
