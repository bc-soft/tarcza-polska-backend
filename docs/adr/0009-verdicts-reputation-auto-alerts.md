# ADR 0009: Werdykt zamknięcia, reputacja zgłaszających, automatyczne alerty i trwała historia incydentu

Status: zaakceptowane, 2026-10-03

## Kontekst

Cztery pozycje z listy post-MVP dotyczą tego samego miejsca w cyklu życia incydentu: co się dzieje, gdy
incydent jest potwierdzony lub zamykany, i jak to wraca do obywateli oraz do wag przyszłych zgłoszeń.

## Decyzja

**Werdykt.** Zamknięcie incydentu niesie `resolution`: `confirmed` (operator, domyślnie), `false_alarm`
(operator) albo `expired` (wygaszenie przez brak aktywności). Werdykt jest zapisany na incydencie,
w historii i w audycie; emituje zdarzenie `IncidentResolved`.

**Reputacja.** `ReputationPolicy` to czyste reguły: zgłaszający +0,10 przy `confirmed`, -0,25 przy
`false_alarm`; odpowiadający na pytania +0,02 / -0,05 w zależności od zgodności z końcowym stanem komórki,
przy czym fałszywy alarm odwraca ocenę (tłum się mylił). Wygaśnięcie jest neutralne. Reputacja żyje na
urządzeniu w przedziale 0..2 i już dziś mnoży wagę zgłoszenia w modelu confidence; urządzenia symulowane
są pomijane. Operator widzi reputację zgłaszających w detalu incydentu.

**Automatyczny alert geograficzny.** Pierwsze przekroczenie poziomu `AUTO_ALERT_LEVEL` (domyślnie
`confirmed`) publikuje alert autora `system` do wszystkich urządzeń w obszarze; treść buduje
`AutoAlertComposer` per typ, z odsetkiem zgodności tłumu. Operator nadal może wysłać własny komunikat.
Wartość inna niż poziom (np. `off`) wyłącza funkcję.

**Historia.** `IncidentEvent` jest append-only; wpisy powstają w serwisach, które powodują zmianę
(klastrowanie, fale, zdjęcia) oraz w handlerach zdarzeń (zmiany obszaru, poziomu, research, źródła, alert,
zamknięcie). Publiczny endpoint pokazuje wszystko poza surowymi dołączeniami zgłoszeń; panel i API operatora
pokazują całość. Dzięki temu „kiedy wykryto, kiedy potwierdzono, jak rósł zasięg” nie jest już składane
z pól pomocniczych.

## Konsekwencje

* Reguły reputacji są jawne i testowane; strojenie to zmiana stałych, bez migracji.
* Fałszywy alarm wymaga świadomej decyzji operatora, bo karze realnych ludzi; UI pyta o potwierdzenie.
* Auto-alert może zaskoczyć przy demo z niskim progiem; domyślnie wymaga `confirmed`, czyli źródła zewnętrznego.
