<?php

declare(strict_types=1);

namespace App\Guidance\Service;

use App\Guidance\Model\Procedure;
use App\Reporting\Enum\ReportType;

/**
 * Built-in safety procedures (Polish). Static on purpose: they must be available in the offline bundle
 * and must not depend on any external system. Extend here; the app caches whatever this returns.
 */
final class ProcedureCatalog
{
    /** @return list<Procedure> highest priority first */
    public function all(): array
    {
        $procedures = [
            new Procedure(
                id: 'power-outage',
                title: 'Brak prądu',
                summary: 'Jak przetrwać kilkugodzinną lub dłuższą przerwę w dostawie energii.',
                steps: [
                    'Sprawdź, czy awaria dotyczy tylko Twojego mieszkania: zajrzyj do skrzynki z bezpiecznikami i zapytaj sąsiadów.',
                    'Odłącz od gniazdek wrażliwe urządzenia (komputer, telewizor, lodówka), żeby skok napięcia przy włączeniu ich nie uszkodził.',
                    'Nie otwieraj lodówki i zamrażarki bez potrzeby: zamknięta zamrażarka trzyma temperaturę do 24–48 godzin.',
                    'Używaj latarek, nie świec. Jeśli musisz użyć świec, nie zostawiaj ich bez nadzoru.',
                    'Oszczędzaj baterię telefonu: tryb oszczędzania energii, wyłącz Bluetooth i niepotrzebne aplikacje.',
                    'Sprawdź, czy starsze lub chore osoby w okolicy potrzebują pomocy (windy, respiratory, koncentratory tlenu).',
                    'Nigdy nie uruchamiaj agregatu ani grilla w zamkniętym pomieszczeniu: tlenek węgla zabija bez ostrzeżenia.',
                ],
                appliesTo: [ReportType::PowerOutage],
                priority: 90,
            ),
            new Procedure(
                id: 'water-outage',
                title: 'Brak wody',
                summary: 'Co zrobić, gdy z kranu nie leci woda albo jest zabarwiona.',
                steps: [
                    'Zakręć krany, żeby po przywróceniu dostaw woda nie zalała mieszkania.',
                    'Jeśli masz wcześniejsze zapasy, racjonuj: minimum 3 litry na osobę dziennie do picia i gotowania.',
                    'Wodę z beczkowozu lub niepewnego źródła gotuj co najmniej 1 minutę przed wypiciem.',
                    'Po przywróceniu dostaw odkręć zimną wodę na kilka minut, aż będzie czysta; mętną wodę zużyj do spłukiwania.',
                    'Sprawdź w aplikacji i na stronie wodociągów, gdzie stoją beczkowozy lub punkty wydawania wody.',
                ],
                appliesTo: [ReportType::WaterOutage],
                priority: 85,
            ),
            new Procedure(
                id: 'fuel-shortage',
                title: 'Brak paliwa',
                summary: 'Jak nie zostać bez możliwości dojazdu, gdy stacje świecą pustkami.',
                steps: [
                    'Nie jedź na stację, żeby dolać do pełna, jeśli masz więcej niż pół baku: kolejki pogłębiają problem.',
                    'Łącz przejazdy z sąsiadami, odłóż podróże, które mogą poczekać.',
                    'Nie magazynuj paliwa w mieszkaniu ani w piwnicy: zagrożenie pożarem i zatruciem oparami.',
                    'Sprawdź w aplikacji, które stacje w okolicy potwierdzono jako działające.',
                ],
                appliesTo: [ReportType::FuelShortage],
                priority: 60,
            ),
            new Procedure(
                id: 'road-blocked',
                title: 'Nieprzejezdna droga',
                summary: 'Zachowanie przy zablokowanej drodze, powalonym drzewie lub zalanym przejeździe.',
                steps: [
                    'Nie wjeżdżaj w wodę na drodze: 30 cm płynącej wody porywa samochód osobowy.',
                    'Nie podchodź do zerwanych przewodów energetycznych; zachowaj co najmniej 10 metrów odstępu i zgłoś na 112.',
                    'Wybierz objazd podany przez służby lub aplikację, nie skracaj drogą polną.',
                    'Jeśli utknąłeś, zostań w pojeździe z włączonymi światłami awaryjnymi, chyba że grozi zalanie lub pożar.',
                ],
                appliesTo: [ReportType::RoadBlocked],
                priority: 70,
            ),
            new Procedure(
                id: 'shelter',
                title: 'Schron i miejsce ukrycia',
                summary: 'Jak skorzystać ze schronu i co zabrać.',
                steps: [
                    'Zabierz dokumenty, leki na kilka dni, wodę, naładowany powerbank, latarkę i ciepłe ubranie.',
                    'Wyłącz gaz i prąd w mieszkaniu, zamknij okna.',
                    'Idź do najbliższego schronu wskazanego w aplikacji; jeśli jest pełny, aplikacja pokaże następny.',
                    'W schronie stosuj się do poleceń osoby odpowiedzialnej, nie blokuj wejść i przejść.',
                    'Potwierdź w aplikacji stan schronu (otwarty, ile miejsc), to pomaga kolejnym osobom.',
                ],
                appliesTo: [ReportType::ShelterIssue],
                priority: 95,
            ),
            new Procedure(
                id: 'siren-alarm',
                title: 'Syrena alarmowa lub alert RCB',
                summary: 'Pierwsze minuty po usłyszeniu syreny albo otrzymaniu alertu.',
                steps: [
                    'Modulowany dźwięk syreny przez 3 minuty to ogłoszenie alarmu; ciągły dźwięk przez 3 minuty to odwołanie.',
                    'Wejdź do najbliższego budynku, oddal się od okien, włącz lokalne radio lub sprawdź oficjalne komunikaty.',
                    'Nie dzwoń na 112 bez konkretnej potrzeby; linie są potrzebne osobom w bezpośrednim zagrożeniu.',
                    'Sprawdź w aplikacji, czy komunikat dotyczy Twojego obszaru, i gdzie jest najbliższy schron.',
                ],
                appliesTo: [ReportType::OtherThreat],
                priority: 100,
            ),
            new Procedure(
                id: 'no-network',
                title: 'Brak zasięgu i internetu',
                summary: 'Jak działać, gdy sieć komórkowa nie odpowiada.',
                steps: [
                    'Wiadomości SMS przechodzą częściej niż połączenia i dane; ustal z bliskimi jeden punkt kontaktowy poza miastem.',
                    'Aplikacja działa na ostatnich zapisanych danych: schrony, procedury i komunikaty są dostępne offline.',
                    'Oszczędzaj baterię: tryb samolotowy i sprawdzanie sieci co 30 minut zamiast ciągłego szukania zasięgu.',
                    'Radio FM w telefonie lub samochodzie to niezależne źródło komunikatów.',
                ],
                priority: 80,
            ),
            new Procedure(
                id: 'evacuation-kit',
                title: 'Plecak ewakuacyjny',
                summary: 'Lista rzeczy, które warto mieć spakowane zawczasu.',
                steps: [
                    'Dokumenty i kopie (także w telefonie), gotówka w małych nominałach.',
                    'Woda 1,5 l na osobę, batony energetyczne, leki stałe na tydzień.',
                    'Latarka, powerbank, kable, radio na baterie, zapasowe baterie.',
                    'Apteczka, maseczki, środek dezynfekujący, koc termiczny.',
                    'Ubranie na zmianę, nakrycie głowy, mocne buty.',
                ],
                priority: 40,
            ),
        ];

        usort($procedures, static fn (Procedure $a, Procedure $b) => $b->priority <=> $a->priority);

        return $procedures;
    }

    /** @return list<Procedure> procedures relevant for a report type, plus the general ones */
    public function forType(ReportType $type): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (Procedure $p) => [] === $p->appliesTo || \in_array($type, $p->appliesTo, true),
        ));
    }
}
