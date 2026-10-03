# ADR 0005: Claude API z web search i JSON schema jako silnik researchu

Status: zaakceptowane, 2026-10-03

## Kontekst

Research incydentu wymaga przeszukania komunikatów operatorów, urzędów i mediów oraz zwrócenia
ustrukturyzowanej listy źródeł. Pisanie własnych scraperów i adapterów RSS nie mieści się w MVP.

## Decyzja

* Oficjalne SDK `anthropic-ai/sdk`, model `claude-opus-5`, jedno wywołanie `messages.create`
  z narzędziem `web_search_20260209` (lista dozwolonych domen, `max_uses` = 6, lokalizacja PL)
  i `output_config.format` = JSON schema.
* Prompt po polsku, w roli analityka CZK; model ma zwrócić pustą listę, gdy nic nie znajdzie.
* Wywołanie w handlerze Messengera, z retry; brak klucza = pominięcie z logiem.
* Interfejs `ResearcherInterface` pozwala podmienić dostawcę lub podstawić atrapę w testach.

## Konsekwencje

* Koszt pomijalny przy skali hackathonu; opóźnienie kilkunastu sekund jest akceptowalne, bo research
  działa w tle i tylko podbija confidence.
* Model nie decyduje o statusie incydentu (patrz ADR 0003).
* Na scenie bez sieci: `tarcza:simulate:confirm` dokłada źródło ręcznie tym samym mechanizmem.
