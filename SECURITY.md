# Security Policy

## Supported Versions
AimTrack volgt de laatste stabiele Laravel 12 releases. Beveiligingsfixes richten zich op de huidige major/minor zolang de codebase wordt onderhouden.

## Kwetsbaarheden melden
1. Meld kwetsbaarheden via **security@aimrack.nl**. Beschrijf impact, scope, reproduceerbare stappen en (indien mogelijk) logs of request samples.
2. Versleutel gevoelige details bij voorkeur met PGP (vraag onze sleutel via hetzelfde adres) en vermeld het commit-hash of release-tag waarop je testte.
3. Dien geen publiek issue in zolang het probleem niet is verholpen; responsible disclosure heeft altijd de voorkeur.

## Respons
- Binnen **2 werkdagen** ontvang je een ontvangstbevestiging vanaf `security@aimrack.nl`.
- Voor kritieke issues mikken we op een fix of mitigatie binnen **14 dagen**; minder kritieke problemen krijgen een realistische planning teruggekoppeld.
- Na release stemmen we publicatie/erkenning af met de melder.

## Richtlijnen voor onderzoekers
- Voer geen data-exfiltratie uit en verstoor productie niet.
- Test uitsluitend met je eigen accounts/data (single-tenant model).
- Respecteer wet- en regelgeving. Meldingen die opzettelijke schade veroorzaken worden niet gehonoreerd.
