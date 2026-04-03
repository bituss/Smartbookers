# SmartBookers REST API - Új Endpointok Tesztelési Útmutató

## 🆕 Új Endpointok (v2.1+)

### 1. Felhasználók Listázása (Paging + Filtering)

**Endpoint:** `GET /api/users/list.php`

```bash
# Alapvetően - első 20 felhasználó
curl http://localhost/Smartbookers/api/users/list.php \
  -b cookies.txt

# Pagination - 10-es lapozás
curl "http://localhost/Smartbookers/api/users/list.php?limit=10&offset=0" \
  -b cookies.txt

# Szűrés szerep alapján
curl "http://localhost/Smartbookers/api/users/list.php?role=provider" \
  -b cookies.txt

# Keresés
curl "http://localhost/Smartbookers/api/users/list.php?search=john" \
  -b cookies.txt

# Csak aktív felhasználók
curl "http://localhost/Smartbookers/api/users/list.php?status=active" \
  -b cookies.txt

# Kombinált: 20/oldal, csak provider-ek, keresés
curl "http://localhost/Smartbookers/api/users/list.php?limit=20&offset=0&role=provider&search=szalon" \
  -b cookies.txt
```

**Válasz Formátum:**
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

### 2. Admin - Felhasználók Listázása (Inaktívakkal)

**Endpoint:** `GET /api/admin/users/list.php`

```bash
# Admin login először
curl -X POST http://localhost/Smartbookers/api/auth/login.php \
  -H "Content-Type: application/json" \
  -c admin_cookies.txt \
  -d '{
    "email": "admin1@admin.hu",
    "password": "admin123"
  }'

# Összes felhasználó (aktív + inaktív)
curl "http://localhost/Smartbookers/api/admin/users/list.php" \
  -b admin_cookies.txt

# Csak inaktívak
curl "http://localhost/Smartbookers/api/admin/users/list.php?status=inactive" \
  -b admin_cookies.txt

# Csak provider-ek listázása
curl "http://localhost/Smartbookers/api/admin/users/list.php?role=provider" \
  -b admin_cookies.txt

# Keresés a deactivated_at-tal
curl "http://localhost/Smartbookers/api/admin/users/list.php?search=john&status=inactive" \
  -b admin_cookies.txt
```

**Válasz (Admin verzió - több info):**
```json
{
  "success": true,
  "data": [
    {
      "id": 5,
      "name": "Test User",
      "email": "test@example.com",
      "role": "user",
      "created_at": "2026-04-03 10:00:00",
      "deactivated_at": "2026-04-03 10:30:00",
      "is_active": false
    }
  ],
  "pagination": { ... }
}
```

---

### 3. Session Törlés (DELETE módszer)

**Endpoint:** `DELETE /api/auth/session.php` (vagy GET/POST fallback)

```bash
# RESTful DELETE
curl -X DELETE http://localhost/Smartbookers/api/auth/session.php \
  -b cookies.txt

# Fallback - GET (böngészőként)
curl http://localhost/Smartbookers/api/auth/logout.php

# Fallback - POST
curl -X POST http://localhost/Smartbookers/api/auth/session.php \
  -b cookies.txt
```

**Válasz:**
```json
{
  "success": true,
  "message": "Sikeres kijelentkezés."
}
```

---

## 📋 Teljes Workflow Teszt

### Felhasználó: Regisztráció → Bejelentkezés → Lista → Kijelentkezés

```bash
#!/bin/bash

echo "1️⃣ Regisztráció..."
curl -X POST http://localhost/Smartbookers/api/auth/register.php \
  -H "Content-Type: application/json" \
  -c cookies.txt \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "TestPass123"
  }'

echo -e "\n\n2️⃣ Bejelentkezés..."
curl -X POST http://localhost/Smartbookers/api/auth/login.php \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -c cookies.txt \
  -d '{
    "email": "test@example.com",
    "password": "TestPass123"
  }'

echo -e "\n\n3️⃣ Felhasználók listázása..."
curl http://localhost/Smartbookers/api/users/list.php?limit=5 \
  -b cookies.txt

echo -e "\n\n4️⃣ Kijelentkezés (DELETE)..."
curl -X DELETE http://localhost/Smartbookers/api/auth/session.php \
  -b cookies.txt

echo -e "\n\n✅ Teszt kész!"
```

---

## Admin Workflow

### Admin: Login → Lista → Inaktiválás → Reaktiválás

```bash
#!/bin/bash

echo "1️⃣ Admin bejelentkezés..."
curl -X POST http://localhost/Smartbookers/api/auth/login.php \
  -H "Content-Type: application/json" \
  -c admin_cookies.txt \
  -d '{
    "email": "admin1@admin.hu",
    "password": "admin123"
  }'

echo -e "\n\n2️⃣ Admin - Összes felhasználó (inaktívakkal)..."
curl "http://localhost/Smartbookers/api/admin/users/list.php?limit=5" \
  -b admin_cookies.txt

echo -e "\n\n3️⃣ Felhasználó inaktiválása (DELETE)..."
curl -X DELETE http://localhost/Smartbookers/api/users/5 \
  -b admin_cookies.txt

echo -e "\n\n4️⃣ Admin - Inaktív felhasználók listázása..."
curl "http://localhost/Smartbookers/api/admin/users/list.php?status=inactive" \
  -b admin_cookies.txt

echo -e "\n\n5️⃣ Felhasználó reaktiválása (PATCH)..."
curl -X PATCH http://localhost/Smartbookers/api/admin/users.php \
  -H "Content-Type: application/json" \
  -b admin_cookies.txt \
  -d '{
    "action": "reactivate",
    "user_id": 5
  }'

echo -e "\n\n6️⃣ Admin - Reaktivált felhasználó (aktív jelzi)..."
curl "http://localhost/Smartbookers/api/admin/users/list.php?search=Test" \
  -b admin_cookies.txt

echo -e "\n\n✅ Admin teszt kész!"
```

---

## 🔍 Postman Gyors Beállítás

Hozz létre ezeket az API requesteket:

**Environment Variables:**
```
base_url = http://localhost/Smartbookers
```

**Pre-request Script (összes request-ben):**
```javascript
// Biztosítja, hogy a cookies az összes request-ben szerepelnek
pm.sendRequest({}, function(err, res) {});
```

**Requests:**

1. **POST** `{{base_url}}/api/auth/register.php`
   - Body: JSON
   ```json
   {
     "name": "Test User",
     "email": "test@example.com",
     "password": "TestPass123"
   }
   ```

2. **POST** `{{base_url}}/api/auth/login.php`
   - Body: JSON (ugyanaz)

3. **GET** `{{base_url}}/api/users/list.php?limit=10&offset=0`
   - Auth: Cookie (csatol az 1-2-ből)

4. **DELETE** `{{base_url}}/api/auth/session.php`
   - Auth: Cookie

5. **GET** `{{base_url}}/api/admin/users/list.php`
   - Auth: Admin cookie

6. **PATCH** `{{base_url}}/api/admin/users.php`
   - Body: JSON
   ```json
   {
     "action": "reactivate",
     "user_id": 5
   }
   ```

---

## ⚠️ Error Responses

### 401 Unauthorized (Nincs bejelentkezés)
```json
{
  "success": false,
  "message": "Nincs bejelentkezés."
}
```

### 403 Forbidden (Nem admin)
```json
{
  "success": false,
  "message": "Admin jogosultság szükséges."
}
```

### 405 Method Not Allowed
```json
{
  "success": false,
  "message": "Csak GET metódus engedélyezett."
}
```

### 422 Unprocessable Entity (Validáció)
```json
{
  "success": false,
  "message": "Email és jelszó mező kötelezőek."
}
```

---

## 🚀 Automata Teszt Shell Script

Fájl: `test_api.sh`

```bash
#!/bin/bash

set -e

BASE_URL="http://localhost/Smartbookers"
COOKIES_FILE="/tmp/cookies.txt"
ADMIN_COOKIES_FILE="/tmp/admin_cookies.txt"

echo "🧪 SmartBookers REST API Tesztek"
echo "================================"

# 1. Felhasználó Regisztráció
echo "1️⃣ Felhasználó Regisztráció..."
REG_RESPONSE=$(curl -s -X POST "$BASE_URL/api/auth/register.php" \
  -H "Content-Type: application/json" \
  -c "$COOKIES_FILE" \
  -d '{"name":"TestUser","email":"test@example.com","password":"TestPass123"}')

if echo "$REG_RESPONSE" | grep -q '"success":true'; then
  echo "✅ Regisztráció sikeres"
else
  echo "❌ Regisztráció sikertelen: $REG_RESPONSE"
  exit 1
fi

# 2. Bejelentkezés
echo "2️⃣ Bejelentkezés..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/api/auth/login.php" \
  -H "Content-Type: application/json" \
  -b "$COOKIES_FILE" \
  -c "$COOKIES_FILE" \
  -d '{"email":"test@example.com","password":"TestPass123"}')

if echo "$LOGIN_RESPONSE" | grep -q '"success":true'; then
  echo "✅ Bejelentkezés sikeres"
  USER_ID=$(echo "$LOGIN_RESPONSE" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)
  echo "   User ID: $USER_ID"
else
  echo "❌ Bejelentkezés sikertelen: $LOGIN_RESPONSE"
  exit 1
fi

# 3. Lista lekérés
echo "3️⃣ Felhasználók listázása..."
LIST_RESPONSE=$(curl -s "$BASE_URL/api/users/list.php?limit=5" -b "$COOKIES_FILE")

if echo "$LIST_RESPONSE" | grep -q '"success":true'; then
  echo "✅ Lista lekérés sikeres"
  COUNT=$(echo "$LIST_RESPONSE" | grep -o '"id"' | wc -l)
  echo "   Felhasználók száma: $COUNT"
else
  echo "❌ Lista lekérés sikertelen"
  exit 1
fi

# 4. Admin Login
echo "4️⃣ Admin Bejelentkezés..."
ADMIN_LOGIN=$(curl -s -X POST "$BASE_URL/api/auth/login.php" \
  -H "Content-Type: application/json" \
  -c "$ADMIN_COOKIES_FILE" \
  -d '{"email":"admin1@admin.hu","password":"admin123"}')

if echo "$ADMIN_LOGIN" | grep -q '"success":true'; then
  echo "✅ Admin bejelentkezés sikeres"
else
  echo "❌ Admin bejelentkezés sikertelen"
  exit 1
fi

# 5. Admin Lista
echo "5️⃣ Admin - Összes felhasználó..."
ADMIN_LIST=$(curl -s "$BASE_URL/api/admin/users/list.php?limit=5" -b "$ADMIN_COOKIES_FILE")

if echo "$ADMIN_LIST" | grep -q '"success":true'; then
  echo "✅ Admin lista sikeres"
else
  echo "❌ Admin lista sikertelen"
  exit 1
fi

echo ""
echo "✅ ÖSSZES TESZT SIKERES!"
```

Futtatás:
```bash
chmod +x test_api.sh
./test_api.sh
```

---

## 📚 Linkek

- [Teljes REST API Dokumentáció](REST_API_DOCUMENTATION.md)
- [API Helper Funkcók](/api/helpers.php)
- [Felhasználó Regisztráció](/api/auth/register.php)
- [Bejelentkezés](/api/auth/login.php)
- [Kijelentkezés](/api/auth/session.php)
- [Felhasználók Listázása](/api/users/list.php)
- [Admin Felhasználók](/api/admin/users/list.php)
