# ADR 0004: Messenger + Scheduler zamiast cronów i synchronicznych łańcuchów

Status: zaakceptowane, 2026-10-03

## Kontekst

Pętla DETECT→VERIFY→MAP→INFORM ma kroki długie (research AI, wysyłka pushy) i cykliczne
(zamykanie fal, planowanie następnego ringu, odpowiedzi symulowanego tłumu).

## Decyzja

* Wszystkie wiadomości `App\*\Message\*` idą asynchronicznie przez Redis; kontrolery tylko zapisują i emitują.
* Zdarzenia domenowe mają wielu odbiorców (`IncidentUpdated` obsługują Confidence i Verification niezależnie).
* Symfony Scheduler (`#[AsSchedule('tarcza')]`) emituje `VerificationTick` co 20 s i `SimulatedCrowdTick`
  co 10 s; jeden worker konsumuje `async` i `scheduler_tarcza`.
* Transport `failed` na Doctrine do inspekcji błędów.

## Konsekwencje

* Brak cronów w kontenerze; skalowanie = więcej workerów.
* Kolejność handlerów tej samej wiadomości nie jest gwarantowana, więc Command publikuje do Mercure
  na osobnym zdarzeniu `IncidentConfidenceUpdated` emitowanym po przeliczeniu.
