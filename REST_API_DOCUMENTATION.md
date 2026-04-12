# SmartBookers REST API - Dokumentáció

## Áttekintés

A SmartBookers alkalmazás teljes mértékben refaktorálásra kerül a REST API követelmények betartásához. Az alábbi dokumentum részletezi az összes megújított API endpointot és a bevezetett soft delete funkcionalitást.

## Adatbázis Módosítások

### Új Mező: `deactivated_at`

Hozzáadva a `users` táblához:
```sql
ALTER TABLE users
ADD COLUMN `deactivated_at` DATETIME DEFAULT NULL AFTER `avatar`;

ALTER TABLE users
ADD INDEX `idx_deactivated_at` (`deactivated_at`);

ALTER TABLE users
ADD INDEX `idx_active_users` (`deactivated_at`, `role`);
```

A `deactivated_at` mező:
- **NULL** = aktív felhasználó
- **Dátum és idő** = inaktiváláskor beállított érték (soft delete)

**Megjegyzés**: A felhasználóhoz tartozó adatok (bookings, messages, stb.) nem törlődnek, csak a fiók lesz inaktiválva.

## REST API Endpointok

### Autentikáció & Regisztráció

#### 1. Felhasználó Bejelentkezés
**Method:** `POST`  
**URL:** `/Smartbookers/api/auth/login.php`  
**Content-Type:** `application/json`

**Request:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Sikeres bejelentkezés!",
  "user": {
    "id": 1,
    "name": "Felhasználó Név",
    "email": "user@example.com",
    "role": "user"
  }
}
```

---

#### 4. Kijelentkezés (Session Törlés)
**Method:** `DELETE`, `POST` vagy `GET`  
**URL:** `/Smartbookers/api/auth/logout.php` vagy `/Smartbookers/api/auth/session.php`  
**Auth:** Nem kötelező (de session-t törli)

**DELETE (RESTful):**
```bash
curl -X DELETE http://localhost/Smartbookers/api/auth/session.php
```

**GET (böngészőből):**
```html
<a href="/Smartbookers/api/auth/logout.php">Kilépés</a>
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Sikeres kijelentkezés."
}
```

---

### Felhasználó Profil Kezelés

#### 5. Felhasználók Listázása (Paging + Filtering)
**Method:** `GET`  
**URL:** `/Smartbookers/api/users/list.php`  
**Auth:** Szükséges (aktív felhasználó)

**Query Parameters:**
- `limit` - Elemek per oldal (default 20, max 100)
- `offset` - Pagination offset (default 0)
- `role` - Szűrés szerep alapján (user, provider, admin)
- `search` - Keresés név vagy email alapján
- `status` - Szűrés aktivitás alapján (active, inactive)

**Examples:**
```
GET /api/users/list.php?limit=10&offset=0
GET /api/users/list.php?role=provider&search=john
GET /api/users/list.php?status=active
```

**Success Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Felhasználó Név",
      "email": "user@example.com",
      "role": "user",
      "created_at": "2026-02-02 17:55:51",
      "is_active": true
    }
  ],
  "pagination": {
    "limit": 10,
    "offset": 0,
    "total": 100,
    "pages": 10
  }
}
```

---

#### 6. Felhasználói Profil Lekérése
**Method:** `GET`  
**URL:** `/Smartbookers/api/users/{id}` vagy `/Smartbookers/api/users/me`  
**Auth:** Szükséges (saját profil vagy admin)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Síkeres lekérés",
  "user": {
    "id": 1,
    "name": "Felhasználó Név",
    "email": "user@example.com",
    "role": "user",
    "avatar": "/Smartbookers/public/images/avatars/a1.png",
    "created_at": "2026-02-02 17:55:51",
    "deactivated_at": null,
    "is_active": true
  }
}
```

---

#### 7. Felhasználói Profil Frissítése
**Method:** `PUT` vagy `PATCH`  
**URL:** `/Smartbookers/api/users/{id}` vagy `/Smartbookers/api/users/me`  
**Content-Type:** `application/json`  
**Auth:** Szükséges (saját profil vagy admin)

**Request:**
```json
{
  "name": "Új Név",
  "avatar": "/Smartbookers/public/images/avatars/a5.png",
  "password": "newpass123"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Felhasználó sikeresen frissítve.",
  "user": { ... }
}
```

---

#### 8. Felhasználó Soft Delete (Inaktiválása)
**Method:** `DELETE`  
**URL:** `/Smartbookers/api/users/{id}` vagy `/Smartbookers/api/users/me`  
**Auth:** Szükséges (saját profil vagy admin)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Felhasználó sikeresen inaktiválva.",
  "user": {
    "id": 1,
    "deactivated_at": "2026-04-03 10:30:00",
    "is_active": false
  }
}
```

---

### Admin Operációk

#### 9. Admin Felhasználók Listázása
**Method:** `GET`  
**URL:** `/Smartbookers/api/admin/users/list.php`  
**Auth:** Szükséges (csak admin)

**Query Parameters:** (Ugyanaz, mint a `/api/users/list.php`)
- `limit`, `offset`, `role`, `search`, `status`

**Speciális:**
- Admin láthatja az inaktív felhasználókat is
- `status=all` - Összes felhasználó (default)
- `status=active` - Csak aktívak
- `status=inactive` - Csak inaktívak

**Success Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Felhasználó Név",
      "email": "user@example.com",
      "role": "user",
      "created_at": "2026-02-02 17:55:51",
      "deactivated_at": null,
      "is_active": true
    }
  ],
  "pagination": { ... }
}
```

---

#### 10. Felhasználó Reaktiválása (Admin Only)
**Method:** `PATCH`  
**URL:** `/Smartbookers/api/admin/users.php`  
**Content-Type:** `application/json`  
**Auth:** Szükséges (csak admin)

**Request:**
```json
{
  "action": "reactivate",
  "user_id": 1
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Felhasználó sikeresen reaktiválva.",
  "user": { ... }
}
```

---

## API Helper Funkcók (`/api/helpers.php`)

Az API standardizált response-okat használ a helper funkciókon keresztül:

```php
// Helytelen: POST helyett valami más
validateMethod(['POST', 'GET']);

// Bejelentkezés ellenőrzése
validateAuth();

// Admin ellenőrzése
validateAdmin();

// JSON Content-Type validáció
validateJsonContent();

// Standardizált válasz küldése
sendJson(true, 'Siker üzenet', ['user' => [...]], 200);
sendJson(false, 'Hiba üzenet', null, 400);
```

---

## REST API Regisztráció & Bejelentkezés (Teljes)

### 2. Felhasználó Regisztráció
**Method:** `POST`  
**URL:** `/Smartbookers/api/auth/register.php`
**Method:** `POST`  
**URL:** `/Smartbookers/api/auth/register.php`  
**Content-Type:** `application/json`

**Request:**
```json
{
  "name": "Felhasználó Név",
  "email": "user@example.com",
  "password": "password123"
}
```

**Success Response (201):**
```json
{
  "success": true,
  "message": "Sikeres regisztráció!",
  "user": {
    "id": 1,
    "name": "Felhasználó Név",
    "email": "user@example.com",
    "role": "user"
  }
}
```

**Validációs Szabályok:**
- Jelszó minimum 6 karakter
- Email érvényes email formátum

---

#### 3. Szolgáltató Regisztráció
**Method:** `POST`  
**URL:** `/Smartbookers/api/auth/register_provider.php`  
**Content-Type:** `application/json`

**Request:**
```json
{
  "name": "Vállalkozó Név",
  "business_name": "Cég Neve",
  "email": "provider@example.com",
  "password": "Pass123!",
  "password_confirm": "Pass123!",
  "phone": "06701234567",
  "service_id": 1,
  "industry_id": 1,
  "zip": "1111",
  "city": "Budapest",
  "utca": "Fő utca",
  "hazszam": "1"
}
```

**Success Response (201):**
```json
{
  "success": true,
  "message": "Sikeres regisztráció!",
  "provider": {
    "id": 1,
    "user_id": 2,
    "name": "Vállalkozó Név",
    "email": "provider@example.com",
    "business_name": "Cég Neve"
  }
}
```

**Validációs Szabályok:**
- Jelszó minimum 6 karakter, 1 nagybetű, 1 szám, 1 speciális karakter
- Irányítószám: 4 számjegy
- Jelszó és megerősítés egyezzen

---

#### 4. Kijelentkezés
**Method:** `POST`  
**URL:** `/Smartbookers/api/auth/logout.php`

**Success Response (200):**
```json
{
  "success": true,
  "message": "Sikeres kijelentkezés."
}
```

---

### Felhasználó Profil Kezelés

#### 5. Felhasználói Profil Lekérése
**Method:** `GET`  
**URL:** `/Smartbookers/api/users/{id}` vagy `/Smartbookers/api/users/me`  
**Auth:** Szükséges (saját profil vagy admin)

**Success Response (200):**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "name": "Felhasználó Név",
    "email": "user@example.com",
    "role": "user",
    "avatar": "/Smartbookers/public/images/avatars/a1.png",
    "created_at": "2026-02-02 17:55:51",
    "deactivated_at": null,
    "is_active": true
  }
}
```

**Error Responses:**
- `401 Unauthorized` - Nincs bejelentkezés
- `403 Forbidden` - Nincs hozzáférés más profiljához (nem admin)
- `404 Not Found` - Felhasználó nem létezik

---

#### 6. Felhasználói Profil Frissítése
**Method:** `PUT` vagy `PATCH`  
**URL:** `/Smartbookers/api/users/{id}` vagy `/Smartbookers/api/users/me`  
**Content-Type:** `application/json`  
**Auth:** Szükséges (saját profil vagy admin)

**Request:**
```json
{
  "name": "Új Név",
  "avatar": "/Smartbookers/public/images/avatars/a5.png",
  "password": "newpass123"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Felhasználó sikeresen frissítve.",
  "user": {
    "id": 1,
    "name": "Új Név",
    "email": "user@example.com",
    "role": "user",
    "avatar": "/Smartbookers/public/images/avatars/a5.png",
    "created_at": "2026-02-02 17:55:51",
    "deactivated_at": null
  }
}
```

**Error Responses:**
- `401 Unauthorized` - Nincs bejelentkezés
- `403 Forbidden` - Nincs hozzáférés
- `404 Not Found` - Felhasználó nem létezik
- `422 Unprocessable Entity` - Validációs hiba

---

#### 7. Felhasználó Soft Delete (Inaktiválása)
**Method:** `DELETE`  
**URL:** `/Smartbookers/api/users/{id}` vagy `/Smartbookers/api/users/me`  
**Auth:** Szükséges (saját profil vagy admin)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Felhasználó sikeresen inaktiválva.",
  "user": {
    "id": 1,
    "deactivated_at": "2026-04-03 10:30:00",
    "is_active": false
  }
}
```

**Error Responses:**
- `401 Unauthorized` - Nincs bejelentkezés
- `403 Forbidden` - Nincs hozzáférés
- `404 Not Found` - Felhasználó nem létezik
- `422 Unprocessable Entity` - Már inaktív a felhasználó

---

### Admin Operációk

#### 8. Felhasználó Reaktiválása (Admin Only)
**Method:** `PATCH`  
**URL:** `/Smartbookers/api/admin/users.php`  
**Content-Type:** `application/json`  
**Auth:** Szükséges (csak admin)

**Request:**
```json
{
  "action": "reactivate",
  "user_id": 1
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Felhasználó sikeresen reaktiválva.",
  "user": {
    "id": 1,
    "name": "Felhasználó Név",
    "email": "user@example.com",
    "role": "user",
    "created_at": "2026-02-02 17:55:51",
    "deactivated_at": null,
    "is_active": true
  }
}
```

**Error Responses:**
- `403 Forbidden` - Nincs admin jogosultság
- `404 Not Found` - Felhasználó nem létezik
- `422 Unprocessable Entity` - Felhasználó már aktív

---

## HTTP Státusz Kódok

| Kód | Leírás |
|-----|--------|
| `200 OK` | Sikeres GET, PUT/PATCH, DELETE |
| `201 Created` | Sikeres POST (new resource) |
| `400 Bad Request` | Hiányzó vagy érvénytelen request paraméter |
| `401 Unauthorized` | Nincs bejelentkezés |
| `403 Forbidden` | Nincs jogosultság az erőforráshoz |
| `404 Not Found` | Erőforrás nem létezik |
| `405 Method Not Allowed` | HTTP metódus nem engedélyezett |
| `422 Unprocessable Entity` | Validációs hiba |
| `500 Internal Server Error` | Szerver hiba |

---

## Biztonsági Megjegyzések

### Inaktív Felhasználók Kizárása

1. **Bejelentkezéskor**: A `/api/auth/login.php` csak akkor engedélyez bejelentkezést, ha `deactivated_at IS NULL`
2. **Session-ben**: A `includes/header.php` és `admin/admin_sidebar.php` ellenőrizik az inaktivitást és kijelentkeztetik a felhasználót
3. **Admin Login**: Az `admin/adminlogin.php` szintén ellenőrzi a `deactivated_at` mezőt

### Jelszó Kezelés

- **User regisztráció**: Minimum 6 karakter
- **Provider regisztráció**: Minimum 6 karakter, 1 nagybetű, 1 szám, 1 speciális karakter
- **Hash módszer**: `password_hash()` (bcrypt)

---

## Frontend Integráció

### HTML Oldalak

Az alábbi oldalak frissítésre kerültek az AJAX API hívások támogatásához:

- **`public/login.php`** - Felhasználó belépés és regisztráció
- **`business/provider_login.php`** - Szolgáltató belépés és regisztráció
- **`admin/users.php`** - Admin felhasználó kezelés (inaktiválás/reaktiválás)

### JavaScript Integrációs Pontok

```javascript
// Bejelentkezés
fetch('/Smartbookers/api/auth/login.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email, password })
})

// Profil szerkesztés
fetch('/Smartbookers/api/users/me', {
  method: 'PUT',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ name, avatar })
})

// Fiók inaktiválása
fetch('/Smartbookers/api/users/me', {
  method: 'DELETE'
})

// Admin reaktiválás
fetch('/Smartbookers/api/admin/users.php', {
  method: 'PATCH',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ action: 'reactivate', user_id })
})
```

---

## Migráció Szükséges Lépések

1. **SQL migráció futtatása**: `migrate_soft_delete.sql`
   ```bash
   mysql -u root idopont_foglalas < migrate_soft_delete.sql
   ```

2. **szerver újraindítás**: Az Apache/PHP cache kiürítéséhez

3. **Teszt bejelentkezés**: Ellenőrizhető az új API endpointokkal

---

## Visszafelé Kompatibilítás

- Az eredeti HTML űrlapok továbbra is működnek AJAX-on keresztül
- A régi POST alapú megközelítések helyett az új REST API-kat használjuk
- Az API válaszok JSON formátumban térnek vissza

---

## Összefoglaló

Ez a refactoring biztosítja, hogy:
✅ Bejelentkezés: GET helyett **POST** használ  
✅ Regisztráció: **POST** dengan JSON  
✅ Felhasználó szerkesztés: **PUT/PATCH** használ  
✅ Felhasználó törlés: **DELETE** soft delete-tel  
✅ Admin reaktiválás: **PATCH** az admin API-val  
✅ Megfelelő HTTP státusz kódok  
✅ Inaktív felhasználók kizárása  
✅ Az adatok nem törlődnek, csak flag beállítása  

