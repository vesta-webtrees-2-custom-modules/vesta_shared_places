��    s      �  �   L      �	  =   �	  :   �	  )   :
  $   d
  -   �
  �  �
  �   9  h   �     =     Z  (   l     �  
   �  &   �     �  7   �  N   ,     {     �  T   �  9   �          3     O     i  �   �  �   X  c     �   q  �   �     �  \   �  r     �     S     5   d  C   �  �   �  "   u     �  �   �  q   3     �  �   �     �  �   �  e   |     �  �   �  �   o  \        t     |  %   �     �     �     �     �            &   +     R     i     ~     �      �  '   �     �       c     /   p  �   �  �   *  #   �  %   �  �      '   �   R   �   I   !  W   Q!  d   �!  \   "  �   k"  �   �"  Q   #  �   �#  �   o$  �   h%  (  b&  �   �'  J   W(  Y   �(  \   �(  R   Y)  \   �)  �   	*  !   �*     �*     �*     �*  @   +     F+  �   c+  �   /,     �,     �,     �,     �,     -     	-  	   -     -     '-  6   +-  	  b-  <   l/  >   �/  &   �/  *   0  *   :0  �  e0  �   �1  g   ~2     �2      3  ,   3     H3     `3  "   p3     �3  B   �3  P   �3     <4  	  L4  O   V5  9   �5     �5     �5  )   6     ;6  �   Y6  �   B7  e   8  �   m8  �   �8     �9  W   �9  g   :  �   }:  e   ;  <   �;  ;   �;  �   �;  "   �<     �<  �   �<  n   L=    �=    �>  #   �?  �   �?  ]   �@     OA  �   VA  �   �A  V   �B     �B     �B  )   	C     3C  	   IC     SC     lC     �C     �C  "   �C     �C     �C     D     D      &D  .   GD     vD     �D  m   �D  1   E  x   AE  �   �E  !   ]F      F  �   �F  3   ,G  \   `G  8   �G  m   �G  s   dH  r   �H  �   KI  �   �I  b   �J  �   �J  �   �K  �   �L  .  �M  �   �N  O   �O  J   �O  ]   ;P  K   �P  ^   �P  }   DQ     �Q     �Q     �Q     R  B   #R     fR  �   �R  �   tS     T  &   T  	   BT  
   LT     WT     [T  	   aT     kT     xT  5   {T           i   J   B   M   ]      1   8      Z               U       p              q   ?   O          P   k   T       (   b   j       n   _               W   Y   :   <      N            "   I   !   6   0   A   m   3   7          X      ;   r          o       /   %       Q   l   [   S                   #       c   
   V   -   \   5   ^         f                             a   h   )             4           $   +   .   G   C      F   g       E   2   K   &   9   L      '       d   D              s          ,   >   @   *           e   =               H   R   	                 `                      (Note: %s higher-level shared places have also been created)  (Note: A higher-level shared place has also been created) %s and the individuals that reference it. ... and fall back to n parent levels A module providing support for shared places. A module supporting shared places as level 0 GEDCOM objects, on the basis of the GEDCOM-L Addendum to the GEDCOM 5.5.1 specification. Shared places may contain e.g. map coordinates, notes and media objects. The module displays this data for all matching places via the extended 'Facts and events' tab. It may also be used to manage GOV ids, in combination with the Gov4Webtrees module. A place name with comma-separated name parts will be resolved to a hierarchy of shared places. Missing higher-level shared places will be created as well. According to the GEDCOM-L Addendum, shared places are referenced via XREFs, just like shared notes etc.  Add %s to the clippings cart Add missing XREFs Additionally link shared places via name All shared place facts Attention! Automatically expand shared place data Create a shared place Create all missing shared places, and add missing XREFs Create missing shared places from tree-independent data, and add missing XREFs Data Fix Determining the link counts (linked individual/families) is expensive when assigning shared places via name, and therefore causes delays when the shared places list is displayed. It's recommended to only select this option if places are assigned via XREFs. Enable the Vesta Places and Pedigree map module to view the shared places hierarchy. Enhance existing shared places with tree-independent data Facts for shared place records GOV-Id for type of location Hierarchize Shared Places Higher-level shared place However, you can still check this option and link shared places via the place name itself. In this case, links are established internally by searching for a shared place with any name matching case-insensitively. If checked, relations between shared places are modelled via an explicit hierarchy, where shared places have XREFs to higher-level shared places, as described in the specification. If this option is checked, shared place data is only displayed for the following facts and events.  If tree-independent data is available, map coordinates from webtrees 'Geographic data' and GOV ids from the Gov4Webtrees module are added. If you are using hierarchical shared places, a place with the name "A, B, C" is mapped to a shared place "A" with a higher-level shared place that maps to "B, C". Important note: In particular, hierarchical shared places do not have names with comma-separated name parts. In particular, map coordinates from webtrees 'Geographic data' and GOV ids from the Gov4Webtrees module are added. It is now recommended to use XREFs, as this improves performance and flexibility. There is a data fix available which may be used to add XREFs.  It is recommended to run the data fix for this custom module to resolve this issue. It is strongly recommended to backup your tree first. It is strongly recommended to switch to hierarchical shared places. It usually will have to be carried out once only, as a migration when switching to hierarchical shared places via the respective configuration option. Linking of shared places to places Location Matching shared places are determined as via the configuration option 'Additionally link shared places via name', including parent levels if set. Matching shared places are determined as via the configuration option 'Additionally link shared places via name'. Missing higher-level shared places are created if required. For this to work without potentially creating duplicates, you have to check 'Automatically accept changes made by this user' in the user administration, at least for the duration of this data fix. Missing shared places are created if required. For this to work without potentially creating duplicates, you have to check 'Automatically accept changes made by this user' in the user administration, at least for the duration of this data fix. Next lower-level shared places Note that the first occurrence may be within a toggleable, currently hidden fact or event (such as an event of a close relative). This will probably be improved in future versions of the module. Note that this also affects the way shared places are created, and the way they are mapped to places. Note: Place names can change over time. You can add multiple names to a shared place, and indicate historic names via a suitable date range. Place names should be entered as a comma-separated list, starting with the smallest place and ending with the country. For example, “Westminster, London, England”. Place names should be entered as single place name (do not use a comma-separated list here). Private Quick shared place facts Restrict to specific facts and events See %1$s for details. Shared place Shared place data Shared place hierarchy Shared place list Shared place name Shared place name (complete hierarchy) Shared place structure Shared place summary Shared places Shared places list Show all shared places in a list Show link counts for shared places list Show shared place hierarchy Summary The created shared places, as well as existing shared places, are linked via XREFs to event places. The location of this shared place is not known. The search cannot be implemented efficiently and may take some time in particular when displaying and updating a large number of records. The search for this data fix currently does not match any records, because the configuration option to 'Use hierarchical shared places' isn't set. The shared place %s already exists. The shared place %s has been created. The summary shows the shared place data, formatted in the same way as for events with a place mapped to the respective shared place. There are various data fixes available. There is a data fix available which may be used to convert existing shared places. Therefore, the place name is displayed here including the full hierarchy. Therefore, this data fix enables you to move away from using that configuration option. This data fix adds XREFs, linking all places within events directly to the respective shared places. This data fix adds tree-independent data (managed outside GEDCOM) to existing shared places. This data fix creates missing shared places, even if no tree-independent data (managed outside GEDCOM) is available for the respective place. This data fix creates missing shared places, if tree-independent data (managed outside GEDCOM) is available for the respective place. This data fix currently won't update anything, because this preference isn't set. This data fix turns shared places with comma-separated name parts into hierarchical shared places (which are linked to higher-level shared places via XREFs). This is the list of GEDCOM facts that your users can add to shared places. You can modify this list by removing or adding fact names as necessary. Fact names that appear in this list must not also appear in the “Unique shared place facts” list. This is the list of GEDCOM facts that your users can add to shared places. You can modify this list by removing or adding fact names as necessary. Fact names that appear in this list must not also appear in the “Unique shared place facts” list.  This is the list of GEDCOM facts that your users can only add once to shared places. For example, if NAME is in this list, users will not be able to add more than one NAME record to a shared place. Fact names that appear in this list must not also appear in the “All shared place facts” list. This leads to inconsistencies when mapping places to shared places, and in general doesn't match the specification for shared places (which earlier versions of this custom module didn't follow strictly). This shared place does not exist or you do not have permission to view it. This shared place has been deleted. The deletion will need to be reviewed by a moderator. This shared place has been deleted. You should review the deletion and then %1$s or %2$s it. This shared place has been edited. The changes need to be reviewed by a moderator. This shared place has been edited. You should review the changes and then %1$s or %2$s them. This tree has shared places with comma-separated name parts, while at the same time the option to 'Use hierarchical shared places' is selected. Type of hierarchical relationship Type of location Unique shared place facts Use hierarchical shared places Use the separate tag '%1$s' in order to model a place hierarchy. View Shared places hierarchy When the preceding option is checked, and no matching shared place is found, fall back to n parent places (so that e.g. for n=2 a place "A, B, C" would also match shared places that match "B, C" and "C") When unchecked, the former approach is used, in which hierarchies are only hinted at by using shared place names with comma-separated name parts. administrative circular shared place hierarchy cultural geographical no other religious shared places yes yes, but only the first occurrence of the shared place Project-Id-Version: Norwegian Bokmål (Vesta Webtrees Custom Modules)
Report-Msgid-Bugs-To: 
POT-Creation-Date: 2020-12-15 23:10+0200
PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE
Last-Translator: FULL NAME <EMAIL@ADDRESS>
Language-Team: Norwegian Bokmål <https://hosted.weblate.org/projects/vesta-webtrees-custom-modules/vesta-shared-places/nb_NO/>
Language: nb_NO
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=2; plural=n != 1;
X-Generator: Weblate 4.4-dev
  (Merk:%s delte steder på høyere nivå er også opprettet)  (Merk: Det er også opprettet et delt sted på høyere nivå) %s og personene som refererer til det. ... og fall tilbake til overordnet nivå n En modul som gir støtte for delte steder. En modul som støtter delte steder som nivå 0 GEDCOM-objekter, basert på GEDCOM-L Addendum til GEDCOM 5.5.1-spesifikaasjonen. Delte steder kan inneholde f.eks. kartkoordinater, notater og mediaobjekter. Modulen viser data for alle steder som matcher via den utvudede "Fakta og hendelser"-fanen. Den kan også brukes til å håndtere GOV-ID's, i kombinasjon med Gov4Webtrees-modulen. Et stedsnavn med kommaadskilte navnedeler blir løst til et hierarki av delte steder. Manglende delte steder på høyere nivå vil også bli opprettet. I følge GEDCOM-L-tillegget refereres det til delte steder via XREF-er, akkurat som delte notater etc.  Add %s til utklippsmappen Legg til manglende XREF-er I tillegg kan du knyttedelte steder via navn Alle fakta om delt sted Merk følgende! Utvid automatisk data om delt sted Opprett et delt sted Opprett alle manglende delte steder, og legg til manglende XREF-er Lag manglende delte steder fra treuavhengige data, og legg til manglende XREF-er Datakorrigering Det er tungvindt å bestemme antall lenker (knyttet person / familie) når man tildeler delte steder via navn, og forårsaker derfor forsinkelser når listen over delte steder vises. Det anbefales å bare velge dette alternativet hvis steder er tildelt via XREF-er. Aktiver Vesta Steder og Stamtavle-modulen for å se strukturen av delte steder. Forbedre eksisterende delte steder med treuavhengige data Fakta for delt stedpost GOV-Id for lokasjonstype Opprett hierarki/struktur av delte steder Delt sted på overodnet nivå Du kan imidlertid fortsatt krysse av dette alternativet og koble delte steder via selve stedsnavnet. I dette tilfellet etableres lenker internt ved å søke etter et delt sted med navn som ikke samsvarer med store og små bokstaver. Hvis avkrysset, forbindelser mellom delte steder blir modellert via en eksplisitt struktur, hvor delte steder har en XREF til et delt sted på et overordnet nivå, som beskrevet i spesifikasjonen. Hvis dette alternativet er merket av, vises data om delt sted bare for følgende fakta og hendelser.  Hvis treuavhengige data er tilgjengelig, legges kartkoordinater fra webtrees 'Geografiske data' og GOV ids fra Gov4Webtrees modulen til. Hvis du bruker hierarkiske eller strukturerte delte steder, blir et sted med navnet "A, B, C" tilordnet et delt sted "A" med et delt nivå på høyere nivå som tilordnes til "B, C". Viktig merknad: Merk spesielt at strukturerte delte steder har ikke navn med kommaseparerte navnedeler. Spesielt legges kartkoordinater fra webtrees 'Geographic data' og GOV ids fra Gov4Webtrees modulen til. Det anbefales nå å bruke XREF, da dette forbedrer ytelsen og fleksibiliteten. Det er en datakorrigering tilgjengelig som kan brukes til å legge til XREF-er.  Det anbefales å kjøre datakorrigering for denne egendefinerte modulen for å løse dette problemet. Det anbefales sterkt å sikkerhetskopiere treet ditt først. Det anbefales sterkt å bytte til hierarkiske delte steder. Det må vanligvis utføres en gang, som en migrering når du bytter til hierarkiske delte steder via det respektive konfigurasjonsalternativet. Kobling av delte steder til steder Sted Matchende delte steder bestemmes som via konfigurasjonsalternativet "I tillegg knyttes delte steder via navn", inkludert overordnet nivå hvis angitt. Matchende delte steder bestemmes som via konfigurasjonsalternativet 'I tillegg knyttes delte steder via navn'. Manglende delte steder på høyere nivå opprettes om nødvendig. For at dette skal fungere uten potensielt å opprette duplikater, må du merke av for "Godta automatisk endringer gjort av denne brukeren" i brukeradministrasjonen, i det minste i løpet av denne dataopprettingen. Manglende delte steder opprettes om nødvendig. For at dette skal fungere uten potensielt å opprette duplikater, må du merke av for "Godta automatisk endringer gjort av denne brukeren" i brukeradministrasjonen, i det minste i løpet av denne datakorrigeringen. Neste delte steder på lavere nivå Vær oppmerksom på at den første forekomsten kan være innenfor et valgbart, for øyeblikket skjult faktum eller en hendelse (for eksempel en hendelse med en nær slektning). Dette vil trolig bli forbedret i fremtidige versjoner av modulen. Merk at dette også påvirker måten delte steder lages, og måten de blir mapped til steder. Notat: Stedsnavn kan endres over tid. Du kan legge til flere navn til et delt sted og indikere historiske navn ved hjelp av et passende datointervall. Stedsnavn må registreres som en kommaseparert liste, med det laveste stedsnavn-nivået først og landnavnet til slutt. For eksempel “Westminster, London, England”. Stedsnavn må registreres som et enkelt stedsnavn (ikke bruk kommaseparert liste her). Privat Hurtigfakta om delte steder Begrens til spesifikke fakta og hendelser Se %1$s for detaljer. Delt sted Informasjon om delt sted Hierarki for delte steder Liste over delte steder Navn på delt sted Delt stedsnavn (komplett hierarki) Delt sted-struktur Oppsummering delte steder Delte steder Liste over delte steder Vis alle delte steder i en liste Vis antall lenker for listen over delte steder Vis struktur for delte steder Oppsummering De opprettede delte stedene, så vel som eksisterende delte steder, er koblet via XREFer til hendelsessteder. Plasseringen av dette delte stedet er ikke kjent. Søket kan ikke implementeres effektivt og kan ta litt tid, spesielt når du viser og oppdaterer et stort antall poster. Søket etter denne dataløsningen samsvarer for øyeblikket ikke med noen poster, fordi konfigurasjonsalternativet "Bruk hierarkiske delte steder" ikke er angitt. Delt sted %s eksisterer allerede. Det delte stedet%s er opprettet. Oppsummeringen viser data om delte steder, formattert på samme måte som for hendelser med et sted mappet til det respektive delte stedet. Det er forskjellige datakorrigeringer tilgjengelig. En dataoppretter er tilgjengelig som kan brukes til å konvertere eksisterende delte steder. Stedsnavnet er derfor vist her inkludert fullt hierarki. Derfor gjør denne datakorrigeringen deg i stand til å gå bort fra å bruke det konfigurasjonsalternativet. Denne dataløsningen legger til XREF-er, og kobler alle steder i hendelser direkte til de respektive delte stedene. Denne datakorrigeringen legger til treuavhengige data (administrert utenfor GEDCOM) til eksisterende delte steder. Denne datakorrigeringen skaper manglende delte steder, selv om ingen treuavhengige data (administrert utenfor GEDCOM) er tilgjengelige for det respektive stedet. Denne datakorrigeringen oppretter manglende delte steder, hvis treuavhengige data (administrert utenfor GEDCOM) er tilgjengelige for det respektive stedet. Denne datakorrigeringen oppdaterer foreløpig ingenting, fordi denne innstillingen ikke er angitt. Denne datakorrigeringen forvandler delte steder med kommaadskilte navnedeler til hierarkiske delte steder (som er knyttet til delte steder på høyere nivå via XREF-er). Dette er listen over GEDCOM-fakta som brukerne dine kan legge til på delte steder. Du kan endre denne listen ved å fjerne eller legge til faktanavn etter behov. Faktanavn som vises i denne listen, må ikke også vises i listen "Unike delte stedfakta". Dette er listen over GEDCOM-fakta som brukerne dine kan legge til på delte steder. Du kan endre denne listen ved å fjerne eller legge til faktanavn etter behov. Faktanavn som vises i denne listen, må ikke også vises i listen "Unike delte stedfakta".  Dette er listen over GEDCOM-fakta som brukerne dine bare kan legge til en gang til delte steder. Hvis NAME for eksempel er i denne listen, vil ikke brukerne kunne legge til mer enn én NAME-post på et delt sted. Faktanavn som vises i denne listen, må ikke vises i listen "Alle fakta om delte steder". Dette fører til uoverensstemmelser ved tilordning av steder til delte steder, og generelt samsvarer ikke med spesifikasjonen for delte steder (som tidligere versjoner av denne egendefinerte modulen ikke fulgte strengt). Dette delte stedet eksisterer ikke, eller du har ikke tillatelse til å se det. Dette delte stedet er slettet. Slettingen må gjennomgås av en moderator. Dette delte stedet er slettet. Du bør se gjennom slettingen og deretter %1$s eller %2$s den. Dette delte stedet er redigert. Endringene må gjennomgås av en moderator. Dette delte stedet er redigert. Du bør gjennomgå endringene og deretter %1$s eller %2$s dem. Dette treet har delte steder med kommadelte navnedeler, samtidig som alternativet «Bruk hierarkiske delte steder» er valgt. Struktur-relasjonstype Lokasjonstype Unike fakta om delte steder Brukt strukturerte delte steder Bruk den separate tag'en '%1$s' for å modellere et stedshierarki. Se struktur for delte steder Når det forrige alternativet er krysset av, og det ikke blir funnet noe samsvarende delt sted, fall tilbake til n overordnede steder (slik at f.eks. For n = 2 et sted "A, B, C" også vil matche delte steder som samsvarer med "B, C" og "C") Når det ikke er merket av, brukes den tidligere tilnærmingen, der hierarkier bare antydes ved å bruke delte stedsnavn med komma-atskilte navnedeler. administrativt sirkulært delt sted-hierarki/struktur kulturell geografisk nei annet religiøs delte steder ja ja, men bare for den første forekomsten av delt sted 