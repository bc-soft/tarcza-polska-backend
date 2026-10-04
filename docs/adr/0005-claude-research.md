# ADR 0005: LLM z web search i JSON schema jako silnik researchu, dostawca wymienny

Status: zaakceptowane, 2026-10-03; uzupełnione tego samego dnia o wybór dostawcy (OpenAI domyślnie)

## Kontekst

Research incydentu wymaga przeszukania komunikatów operatorów, urzędów i mediów oraz zwrócenia
ustrukturyzowanej listy źródeł. Pisanie własnych scraperów i adapterów RSS nie mieści się w MVP.

## Decyzja

* Jedno wywołanie modelu z wbudowanym wyszukiwaniem w sieci (lista dozwolonych domen, maks. 6 wyszukiwań,
  lokalizacja PL) i odpowiedzią wymuszoną ścisłym JSON schema.
* Prompt, schema, lista domen i parser są wspólne (`ResearchPrompt`); dostawcy różnią się tylko klientem:
  `OpenAiResearcher` (Responses API, `web_search`, `json_schema` strict) i `ClaudeResearcher`
  (`messages.create`, `web_search_20260209`, `output_config.format`).
* Dostawcę wybiera `RESEARCH_PROVIDER` (`openai` domyślnie; `anthropic`; `none`) przez `ResearcherFactory` zarejestrowaną jako fabrykę `ResearcherInterface`.
* Prompt po polsku, w roli analityka CZK; model ma zwrócić pustą listę, gdy nic nie znajdzie.
* Wywołanie w handlerze Messengera, z retry; brak klucza = pominięcie z logiem.

## Konsekwencje

* Koszt pomijalny przy skali hackathonu; opóźnienie kilkunastu sekund jest akceptowalne, bo research
  działa w tle i tylko podbija confidence.
* Model nie decyduje o statusie incydentu (patrz ADR 0003).
* Na scenie bez sieci: `tarcza:simulate:confirm` dokłada źródło ręcznie tym samym mechanizmem.
* Filtr domen działa u obu dostawców nieco inaczej (OpenAI: maks. 20 domen); po zmianie dostawcy
  warto przejrzeć pierwsze odpowiedzi i w razie potrzeby zaostrzyć prompt.
