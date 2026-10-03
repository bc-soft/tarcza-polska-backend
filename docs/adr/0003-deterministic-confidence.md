# ADR 0003: Deterministyczny model confidence, AI tylko jako dostawca dowodów

Status: zaakceptowane, 2026-10-03

## Kontekst

Produkt obiecuje, że „confidence nie powinien być prostą liczbą generowaną przez LLM”. Jury ocenia
zrozumienie systemu; operator musi umieć wytłumaczyć, skąd wzięło się 76%.

## Decyzja

`ConfidenceCalculator` to czysta funkcja z jawnie nazwanymi wagami (raporty, rozproszenie, tłum,
świeżość, źródła zewnętrzne), progami poziomów i regułą, że CONFIRMED wymaga źródła zewnętrznego.
Każde przeliczenie zapisuje rozbicie na składowe w incydencie. Claude dostarcza wyłącznie listę
źródeł z oceną wiarygodności; nie zmienia wyniku bezpośrednio.

## Konsekwencje

* Model jest testowalny jednostkowo (`ConfidenceCalculatorTest`) i strojony stałymi.
* Zmiana wag nie wymaga migracji: breakdown jest JSON-em.
* Odpowiedzi tłumu mogą obniżyć confidence (ujemny składnik), co chroni przed fałszywymi alarmami.
