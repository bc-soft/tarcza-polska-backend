# ADR 0001: Modularny monolit w Symfony zamiast mikroserwisów

Status: zaakceptowane, 2026-10-03

## Kontekst

Hackathon, 48 godzin, zespół backendowy znający Symfony. Produkt ma kilka wyraźnych domen
(zgłoszenia, incydenty, weryfikacja, confidence, research, alerty, schrony) i asynchroniczny przepływ.

## Decyzja

Jedna aplikacja Symfony 7.4 z modułami w `src/<Moduł>/` (encje, serwisy, kontrolery, handlery per moduł),
komunikacja między modułami przez zdarzenia domenowe w Symfony Messenger. Brak osobnych serwisów,
brak wspólnego „Entity/” i „Service/”.

## Konsekwencje

* Jeden deploy, jedna baza, jedna konfiguracja; cała pętla da się uruchomić z komendy konsolowej.
* Granice modułów są konwencją, nie barierą techniczną: odczyt cudzych repozytoriów jest dopuszczalny
  (np. mapa składa incydenty, schrony i alerty), zapis tylko przez własny moduł lub zdarzenie.
* Gdyby po hackathonie zaszła potrzeba, moduł (np. Intelligence) da się wynieść bez przepisywania reszty,
  bo komunikuje się wyłącznie przez wiadomości.
