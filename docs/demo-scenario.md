# Scenariusz demo (3 minuty)

Cel: pokazać pętlę DETECT → VERIFY → MAP → INFORM i to, że system sam dopytuje ludzi.

## Przygotowanie (przed wejściem na scenę)

1. `make demo` (stack, schrony, stacje, konta, dane demo), panel otwarty na https://localhost/command, zalogowany operator.
2. Worker działa (`make worker` w drugim oknie, warto pokazać logi).
3. Dwa telefony z aplikacją zarejestrowane i z lokalizacją ustawioną na Jeżyce (52.4121, 16.9012).
4. Jeśli jest internet i klucz API: `OPENAI_API_KEY` w `.env.local` (albo `ANTHROPIC_API_KEY` z
   `RESEARCH_PROVIDER=anthropic`). Jeśli nie: przygotuj `make console c="tarcza:simulate:confirm"` w terminalu.

## Przebieg

| Krok | Akcja | Co widać |
|---|---|---|
| 1 | `make simulate` | W panelu pojawia się incydent „Brak prądu”, status `detected`, confidence ~0.36 (LIKELY). Logi: `Report ... created incident`, `joined incident`. |
| 2 | Czekamy ~10 s | Log `wave ring 0 -> 7 cells, N devices`. Telefony dostają pytanie „Czy w tej chwili masz dostęp do prądu?”. |
| 3 | Prowadzący odpowiada NIE na telefonie | Hex pod telefonem robi się czerwony, confidence rośnie. Symulowany tłum odpowiada w tle. |
| 4 | Po 90 s | Fala zamknięta, obszar (biała przerywana) rośnie, kolejna fala pyta komórki na granicy (szare). |
| 5 | Kolejne fale | Na zewnątrz prawdziwej awarii odpowiedzi „mam prąd” kolorują hexy na zielono. Granica się domyka. Status `active`. |
| 6 | Research AI (auto) lub `tarcza:simulate:confirm` | Źródło operatora w panelu, confidence skacze do CONFIRMED, przeliczenie widać live. |
| 7 | Operator wysyła komunikat z formularza w detalu incydentu | Telefon w obszarze dostaje push „Potwierdzono: brak prądu w Twojej okolicy”. Telefon poza obszarem nie. |

## Zdania do powiedzenia

* „Pojedyncze zgłoszenie to nie fakt. System buduje hipotezę i sam zbiera brakujące dane.”
* „Confidence nie jest liczbą z modelu językowego. To deterministyczny model, a tu jest jego rozbicie.”
* „Obywatel widzi poligon i poziom pewności. Operator widzi źródła. To ten sam system, dwa poziomy szczegółowości.”

## Reset między próbami

```bash
make console c="tarcza:fixtures:load --reset"   # czyści incydenty, zgłoszenia, fale, alerty, symulowane urządzenia i wgrywa dane demo od nowa
make simulate                                   # i scenariusz na żywo jeszcze raz
```
