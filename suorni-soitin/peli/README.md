# Taajuus – radiotycoon-peli (v0.4)

Itsenäinen PWA (yksi index.html, ei riippuvuuksia, tallennus localStorageen).
Osoite (päätetty 14.11.2026, kestolinkit pysyvät ennallaan): https://suorni.fi/?peli=1
Lisäosan tehtävä: kun pyynnössä on `?peli=1`, lähetä tämän kansion index.html sivuna (esim. `template_redirect`, `<base href>` osoittamaan lisäosan peli-kansioon, jotta manifest ja kuvakkeet löytyvät). Staattiset tiedostot toimivat suoraan lisäosan kansiosta.
Peli hakee kappaleet samalta sivustolta: /wp-json/suorni/v1/tracks (varapolku ?rest_route=).
Suunnitelma ja jatko: Radio Suorni -projektin dokumentit claude/taajuus-peli-suunnitelma.md ja claude/koodaus-taajuus-siirto.md.
