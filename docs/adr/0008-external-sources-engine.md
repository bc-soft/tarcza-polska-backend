# ADR 0008: External Sources Engine - deterministyczne dopasowanie kanałów RSS/Atom do incydentów

Status: zaakceptowane, 2026-10-03

## Kontekst

Research AI (ADR 0005) działa raz na incydent i kosztuje. Komunikaty operatorów, urzędów i mediów pojawiają
się jednak w czasie, często kilkanaście minut po pierwszych zgłoszeniach. Potrzebny jest tani mechanizm,
który stale obserwuje źródła publiczne i dokłada dowody, gdy się pojawią.

## Decyzja

* Lista kanałów w `config/packages/external_sources.yaml` (nazwa, URL, rodzaj, wiarygodność). Start:
  trzy ogólnopolskie serwisy informacyjne; kanały operatorów infrastruktury dopisuje się po potwierdzeniu adresów.
* `FeedAdapterInterface` (serwisy tagowane, wykrywane automatycznie) z pierwszą implementacją `RssFeedAdapter`
  (RSS 2.0 i Atom, tolerancyjny parser, bez rozwijania encji i bez sieci w parserze). Adapter do API
  operatora energetycznego implementuje ten sam kontrakt.
* `FeedRegistry` pobiera wszystkie kanały raz na interwał i cache'uje wynik; awaria jednego kanału nie
  zatrzymuje pozostałych.
* `IncidentFeedMatcher` jest deterministyczny: słowa kluczowe typu (polskie rdzenie), tokeny miejsca
  z reverse geocodingu z prostym stemmingiem („Poznań” łapie „Poznaniu”), okno czasowe od startu incydentu.
  Dopasowanie zapisuje, które słowa zadziałały, więc operator widzi uzasadnienie.
* `ExternalSourcesTick` co 5 minut w Schedulerze; trafienia idą przez `ExternalSourceRecorder`
  (`found_by = rss`, wiarygodność kanału lekko ważona siłą dopasowania), więc confidence rośnie tą samą
  ścieżką co przy researchu AI i źródłach dodanych ręcznie. Deduplikacja po URL w obrębie incydentu.
* `tarcza:sources:poll` do ręcznego podglądu i wymuszenia przebiegu.

## Konsekwencje

* Zero kosztu tokenów, przewidywalne zachowanie, łatwe testy jednostkowe parsera i matchera.
* Dopasowanie po słowach kluczowych bywa nadgorliwe przy dużych miastach; wiarygodność mediów (0,7) i wymóg
  trafienia zarówno typu, jak i miejsca ograniczają fałszywe potwierdzenia, a operator może źródło zignorować.
* Lista kanałów jest konfiguracją, nie kodem: zespół może ją rozszerzać podczas wdrożenia u partnera.
