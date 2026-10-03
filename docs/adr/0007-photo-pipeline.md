# ADR 0007: Zdjęcia obywateli - sanityzacja na serwerze, prywatna galeria, analiza wizji za przełącznikiem

Status: zaakceptowane, 2026-10-03

## Kontekst

Opis produktu (post-MVP) przewiduje zdjęcia jako materiał weryfikacyjny: usuwanie EXIF, klasyfikację,
moderację i powiązanie z incydentem. Zdjęcie z telefonu niesie współrzędne GPS i identyfikatory urządzenia,
a obywatel nie powinien ich przekazywać dalej niż do naszego serwera. Operator musi widzieć zdjęcia, obywatel nie.

## Decyzja

* `POST /api/v1/reports/{id}/photo` (multipart, JPEG/PNG/WebP do 10 MB, maks. 3 na zgłoszenie, rate limit).
* `ImageSanitizer` przepuszcza każdy obraz przez GD: stosuje orientację EXIF, skaluje dłuższy bok do 1600 px,
  spłaszcza przezroczystość na biało i zapisuje czysty baseline JPEG. Metadane (EXIF, GPS, ICC, XMP, miniatury)
  nie przeżywają re-enkodowania, bez parsowania ich ręcznie.
* Plik trafia do Flysystem `photos.storage` (lokalny dysk; adapter S3/R2 to zmiana w jednym pliku YAML).
  Encja `ReportPhoto` trzyma ścieżkę, wymiary, rozmiar, SHA-256 i wynik analizy.
* Analiza jest asynchroniczna (`PhotoUploaded` → `PhotoAnalyzerInterface`). Dostawcę wybiera ten sam
  `RESEARCH_PROVIDER` co research: `openai` daje analizę wizji (Responses API, obraz w `detail: low`, ścisły
  JSON: `relevant`, `matchesType`, `description`, `unsafe`, `confidence`), inne wartości dają `NullPhotoAnalyzer`
  i zdjęcie jest tylko pokazywane.
* Dostęp do pliku wyłącznie przez uwierzytelnione endpointy operatora (API z JWT i panel z sesją), każdy
  odczyt w `audit_log`. Zdjęcia oznaczone przez moderację są domyślnie ukryte w galerii.
* Wynik analizy nie wpływa jeszcze na confidence (YAGNI); jest dowodem dla operatora i wpisem w historii incydentu.

## Konsekwencje

* Żadne surowe zdjęcie z telefonu nie jest przechowywane; nie da się z naszych plików odzyskać pozycji GPS.
* Koszt analizy to jedno krótkie wywołanie wizji na zdjęcie; bez klucza funkcja degraduje do galerii.
* HEIC z iPhone'a trzeba skonwertować po stronie aplikacji (image_picker domyślnie oddaje JPEG).
