# Rendszerarchitektúra és Fejlesztési Irányelvek (AI-hoz optimalizálva)

Ez a dokumentum a projekt hivatalos kódolási, architekturális és együttműködési szabályait tartalmazza. Bármilyen új funkció fejlesztése vagy meglévő kód refaktorálása (akár humán, akár AI által) **szigorúan ezen elvek betartásával** történhet.

## 1. Kód Integritása és Formátum (Kritikus AI Szabályok)

- **Fájlok teljessége:** Minden egyes kódmódosítás után a **teljes, komplett fájlt** kell generálni. Tilos a kódrészletek használata, a csonkítás, vagy a `// ... existing code ...` (és hasonló) helykitöltők alkalmazása. A cél a folyamatosan futtatható kódbázis fenntartása vágólapról történő másolás után.

- **Fejlécezés és Verziózás:** Minden fájl elején (a nyitó tag után) kommentben rögzíteni kell a fájl pontos helyét a mappaszerkezetben, a funkcióját, és a generálás/módosítás pontos időpontját (formátum: `YYYY. hónap DD. HH:MM:SS` magyar idő szerint).

- **Egy fájl - Egy felelősség:** A funkciókat saját fájlokban, saját szerepkörrel kell elhelyezni (Single Responsibility Principle).

## 2. Backend Architektúra (PHP 8.3+)

- **Szigorú Típusok (Strict Types):** **Minden** PHP fájl legelső utasítása a nyitó `<?php` tag után a `declare(strict_types=1);` kell, hogy legyen.

- **Explicit Típusdeklarációk:** Minden függvény paraméterének és visszatérési értékének szigorúan típusosnak kell lennie (pl. `int`, `string`, `bool`, `array`, `void`). Ha egy paraméter lehet null, azt `?` jellel kell jelölni.

- **PHPDoc használata:** Mivel a PHP natívan nem támogatja a generikus tömbök típusozását (pl. `array of strings`), az asszociatív tömböket (`$data`) váró vagy visszaadó függvényeknél **kötelező** a PHPDoc (`/** @param array<string, mixed> ... */`) használata, hogy az AI ismerje az elvárt struktúrát.

- **PHP Osztályok betöltése (Autoloader):** A projekt egyedi, dinamikus `Autoloader`-t használ. **Szigorúan tilos** a `require_once` vagy `include` használata az osztályok (Handlerek, Service-ek, Repository-k stb.) betöltésére a fájlok elején! Az osztályok automatikusan betöltődnek a fájlnév (snake_case, pl. `order_handler.php`) és az osztálynév (PascalCase, pl. `OrderHandler`) egyezése alapján.

- **Záró Tag mellőzése:** A tisztán PHP kódot tartalmazó fájlok végéről kötelező elhagyni a `?>` záró taget a "headers already sent" hibák elkerülése végett.

## 3. Frontend Architektúra és Állapotkezelés

- **Tiszta Alpine.js:** A frontend reaktivitást, a modálokat és a táblázatokat **kizárólag Alpine.js állapotok (state)** vezérlik.

- **Tilos a Vanilla JS DOM manipuláció:** Szigorúan tilos a `document.getElementById()`, `.innerHTML`, `.innerText`, vagy `.classList.add()` használata adatok megjelenítésére ott, ahol Alpine.js fut. A DOM-ot csak deklaratív direktívákkal (`x-model`, `x-text`, `x-show`, `x-if`) szabad frissíteni.

- **"Csontváz" (Skeleton) Objektumok:** Az Alpine.js `Cannot read properties of null` hibáinak és a DOM animációs összeomlások elkerülése végett a változókat kiürítéskor sosem `null`-ra, hanem üres/alapértelmezett kulcsokat tartalmazó "csontváz" objektumokra (pl. `{ id: null, status: '' }`) kell állítani.

- **JS és CSS fájlok betöltése (AssetLoader):** Tilos beágyazott (inline) `<style>` és `<script>` blokkokat hagyni a PHP (HTML) nézetekben. A stílusok a `.css`, a logikák a `.js` fájlokba valók az `assets/` mappában. Betöltésüket **kizárólag** a backend `AssetLoader` osztálya végezheti el (`AssetLoader::enqueueJs()`, `AssetLoader::enqueueCss()`, majd a nézet végén `AssetLoader::renderJs()`, `AssetLoader::renderCss()`). Ez megakadályozza a fájlok duplikált betöltését és automatikusan kezeli a gyorsítótárazást (cache-busting).

- **PHP és JS elválasztása:** JavaScript fájlokba tilos PHP kódot (`<?php echo ... ?>`) írni. A szükséges backend változókat (pl. `BASE_URL`) a HTML fejlécben egy globális `window` objektumban, vagy a DOM elemek `data-*` attribútumaiban kell átadni a JS-nek.

## 4. Hibakezelés, Logolás és Debugolás (AI-Optimalizált)

- **Strukturált Naplózás:** Hibák elkapásakor a beépített `writeLog()` függvényt kell használni. A logoknak tartalmazniuk kell a HTTP kontextust (URL, Method), az azonosított User ID-t, a kapott adatokat (JSON payload), és – ha van – az Exception teljes Stack Trace-ét.

- **Központi Kivételkezelés:** Kerülni kell a túlzott, néma `try-catch` blokkokat (amik elnyelik a hibát). A végzetes hibákat engedni kell felbuborékolni az `ApiKernel` globális kivételkezelőjébe (`set_exception_handler`), amely lementi a részleteket a `debug.log`-ba, és biztonságos 500-as JSON választ ad a kliensnek.

- **Kliens-oldali hibák:** A frontend JavaScript (és Alpine.js) hibákat a beépített globális `window.onerror` eseménykezelő automatikusan felküldi a backendnek (`navigator.sendBeacon` segítségével), így a kliensnél fellépő hibák is megjelennek a szerveroldali logokban.

## 5. Biztonság

- **Adatbázis:** Kizárólag a `Database` Singleton osztályon keresztül, PDO Prepared Statements (`?` paraméterkötés) használatával szabad az adatbázishoz férni. Tilos a változók közvetlen beillesztése SQL lekérdezésekbe.

- **Környezeti Változók:** Minden API kulcs, jelszó és környezet-specifikus adat (URL, DB adatok) a projekt gyökerében lévő `.env` fájlban tárolandó. Ezeket az `env()` segédfüggvénnyel kell kiolvasni.

- **XSS Védelem:** A backendből érkező, HTML-be írt változókat mindig escape-elni kell (`htmlspecialchars`). Az Alpine.js `x-text` direktívája ezt automatikusan elvégzi, de nyers HTML (`x-html` vagy `echo`) esetén kötelező a szűrés.

## 6. Szabályok az AI és a Felhasználó együttműködéséhez

- **Kontextus biztosítása:** Mielőtt az AI (Gemini) módosításokat végez, a felhasználónak biztosítania kell, hogy az AI a legfrissebb, teljes kódbázissal vagy a releváns fájlok teljes tartalmával rendelkezzen.

- **Fals hivatkozások felülbírálása:** Ha az AI egy fájl csonka vagy régebbi verziójára hivatkozik, a felhasználónak azonnal felül kell bírálnia azt a teljes, aktuális fájlverzió feltöltésével a hibás implementációk elkerülése végett.
