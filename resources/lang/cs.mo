��    q      �  �   ,      �	  =   �	  :   �	  )   
  $   ,
  -   Q
  �  
  �     h   �          "  (   4     ]  
   t  &        �  7   �  N   �     C     L  T   M  9   �     �     �          1  �   K  �      c   �  �   9  �   �     g  \   w  r   �  �   G  S   �  5   ,  C   b  �   �  "   =     `  �   i  q   �     m  �   n     b  �   �  e   D     �  �   �  �   7  \   �     <     D  %   ]     �     �     �     �     �     �  &   �          1     F     T  '   g     �  c   �  /   �  �   +  �   �  #   H  %   l  �   �  '      R   ?   I   �   W   �   d   4!  \   �!  �   �!  �   �"  Q   
#  �   \#  �   �#  �   �$  (  �%  �   '  J   �'  Y   -(  \   �(  R   �(  \   7)  �   �)  !   $*     F*     W*     q*  @   �*     �*  �   �*  �   �+     L,     [,     {,     �,     �,     �,  	   �,     �,     �,  6   �,  �  �,  N   �.  J   /  (   g/  2   �/  1   �/  �  �/  �   �1  u   *2  %   �2     �2  2   �2  !   3     63  .   =3     l3  M   �3  i   �3     ?4  +  N4  g   z5  G   �5  $   *6     O6  &   g6  "   �6  �   �6  �   �7  h   b8  �   �8  �   {9     4:  \   K:  �   �:  �   -;  j   �;  5   5<  D   k<  �   �<  %   M=     s=  �   z=  �   6>  .  �>    �?  -   A  �   :A  k   B  
   �B  �   �B  �   3C  i   �C  	   GD  !   QD  $   sD     �D     �D     �D     �D     �D     E  .   .E     ]E     zE     �E     �E  4   �E     �E  z   �E  .   yF  �   �F  �   1G  !   �G  "   �G  �   H  (   �H  L   �H  B   I  W   TI  �   �I  ~   2J  �   �J  �   aK  H   L  �   UL     M  !  2N  <  TO  �   �P  N   cQ  D   �Q  ]   �Q  G   UR  ^   �R  �   �R     �S     �S  $   �S  '   �S  ;   T  %   NT  �   tT  �   eU     V  (   V  	   GV     QV     ]V     `V     fV     tV     �V  .   �V     F       -       L   :       S   T       B      8   m   X   
   p   e          4              <   j   7   ]   9   Y   q   h       Z                C           b   d   >   @   ;      .           5       )   `           6      M   A          D             H   P   !      3          =   I   l          %   _      a   n   [   $   V       "   k   E         g      f   ,      Q           +   R                  N   *   W         2   1   	   o   '      J                  (                  #   ^   i           U       \       O       /       G      &          K   0      c   ?     (Note: %s higher-level shared places have also been created)  (Note: A higher-level shared place has also been created) %s and the individuals that reference it. ... and fall back to n parent levels A module providing support for shared places. A module supporting shared places as level 0 GEDCOM objects, on the basis of the GEDCOM-L Addendum to the GEDCOM 5.5.1 specification. Shared places may contain e.g. map coordinates, notes and media objects. The module displays this data for all matching places via the extended 'Facts and events' tab. It may also be used to manage GOV ids, in combination with the Gov4Webtrees module. A place name with comma-separated name parts will be resolved to a hierarchy of shared places. Missing higher-level shared places will be created as well. According to the GEDCOM-L Addendum, shared places are referenced via XREFs, just like shared notes etc.  Add %s to the clippings cart Add missing XREFs Additionally link shared places via name All shared place facts Attention! Automatically expand shared place data Create a shared place Create all missing shared places, and add missing XREFs Create missing shared places from tree-independent data, and add missing XREFs Data Fix Determining the link counts (linked individual/families) is expensive when assigning shared places via name, and therefore causes delays when the shared places list is displayed. It's recommended to only select this option if places are assigned via XREFs. Enable the Vesta Places and Pedigree map module to view the shared places hierarchy. Enhance existing shared places with tree-independent data Facts for shared place records GOV-Id for type of location Hierarchize Shared Places Higher-level shared place However, you can still check this option and link shared places via the place name itself. In this case, links are established internally by searching for a shared place with any name matching case-insensitively. If checked, relations between shared places are modelled via an explicit hierarchy, where shared places have XREFs to higher-level shared places, as described in the specification. If this option is checked, shared place data is only displayed for the following facts and events.  If tree-independent data is available, map coordinates from webtrees 'Geographic data' and GOV ids from the Gov4Webtrees module are added. If you are using hierarchical shared places, a place with the name "A, B, C" is mapped to a shared place "A" with a higher-level shared place that maps to "B, C". Important note: In particular, hierarchical shared places do not have names with comma-separated name parts. In particular, map coordinates from webtrees 'Geographic data' and GOV ids from the Gov4Webtrees module are added. It is now recommended to use XREFs, as this improves performance and flexibility. There is a data fix available which may be used to add XREFs.  It is recommended to run the data fix for this custom module to resolve this issue. It is strongly recommended to backup your tree first. It is strongly recommended to switch to hierarchical shared places. It usually will have to be carried out once only, as a migration when switching to hierarchical shared places via the respective configuration option. Linking of shared places to places Location Matching shared places are determined as via the configuration option 'Additionally link shared places via name', including parent levels if set. Matching shared places are determined as via the configuration option 'Additionally link shared places via name'. Missing higher-level shared places are created if required. For this to work without potentially creating duplicates, you have to check 'Automatically accept changes made by this user' in the user administration, at least for the duration of this data fix. Missing shared places are created if required. For this to work without potentially creating duplicates, you have to check 'Automatically accept changes made by this user' in the user administration, at least for the duration of this data fix. Next lower-level shared places Note that the first occurrence may be within a toggleable, currently hidden fact or event (such as an event of a close relative). This will probably be improved in future versions of the module. Note that this also affects the way shared places are created, and the way they are mapped to places. Note: Place names can change over time. You can add multiple names to a shared place, and indicate historic names via a suitable date range. Place names should be entered as a comma-separated list, starting with the smallest place and ending with the country. For example, “Westminster, London, England”. Place names should be entered as single place name (do not use a comma-separated list here). Private Quick shared place facts Restrict to specific facts and events See %1$s for details. Shared place Shared place data Shared place hierarchy Shared place list Shared place name Shared place name (complete hierarchy) Shared place structure Shared place summary Shared places Shared places list Show link counts for shared places list Summary The created shared places, as well as existing shared places, are linked via XREFs to event places. The location of this shared place is not known. The search cannot be implemented efficiently and may take some time in particular when displaying and updating a large number of records. The search for this data fix currently does not match any records, because the configuration option to 'Use hierarchical shared places' isn't set. The shared place %s already exists. The shared place %s has been created. The summary shows the shared place data, formatted in the same way as for events with a place mapped to the respective shared place. There are various data fixes available. There is a data fix available which may be used to convert existing shared places. Therefore, the place name is displayed here including the full hierarchy. Therefore, this data fix enables you to move away from using that configuration option. This data fix adds XREFs, linking all places within events directly to the respective shared places. This data fix adds tree-independent data (managed outside GEDCOM) to existing shared places. This data fix creates missing shared places, even if no tree-independent data (managed outside GEDCOM) is available for the respective place. This data fix creates missing shared places, if tree-independent data (managed outside GEDCOM) is available for the respective place. This data fix currently won't update anything, because this preference isn't set. This data fix turns shared places with comma-separated name parts into hierarchical shared places (which are linked to higher-level shared places via XREFs). This is the list of GEDCOM facts that your users can add to shared places. You can modify this list by removing or adding fact names as necessary. Fact names that appear in this list must not also appear in the “Unique shared place facts” list. This is the list of GEDCOM facts that your users can add to shared places. You can modify this list by removing or adding fact names as necessary. Fact names that appear in this list must not also appear in the “Unique shared place facts” list.  This is the list of GEDCOM facts that your users can only add once to shared places. For example, if NAME is in this list, users will not be able to add more than one NAME record to a shared place. Fact names that appear in this list must not also appear in the “All shared place facts” list. This leads to inconsistencies when mapping places to shared places, and in general doesn't match the specification for shared places (which earlier versions of this custom module didn't follow strictly). This shared place does not exist or you do not have permission to view it. This shared place has been deleted. The deletion will need to be reviewed by a moderator. This shared place has been deleted. You should review the deletion and then %1$s or %2$s it. This shared place has been edited. The changes need to be reviewed by a moderator. This shared place has been edited. You should review the changes and then %1$s or %2$s them. This tree has shared places with comma-separated name parts, while at the same time the option to 'Use hierarchical shared places' is selected. Type of hierarchical relationship Type of location Unique shared place facts Use hierarchical shared places Use the separate tag '%1$s' in order to model a place hierarchy. View Shared places hierarchy When the preceding option is checked, and no matching shared place is found, fall back to n parent places (so that e.g. for n=2 a place "A, B, C" would also match shared places that match "B, C" and "C") When unchecked, the former approach is used, in which hierarchies are only hinted at by using shared place names with comma-separated name parts. administrative circular shared place hierarchy cultural geographical no other religious shared places yes yes, but only the first occurrence of the shared place Project-Id-Version: Czech (Vesta Webtrees Custom Modules)
Report-Msgid-Bugs-To: 
PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE
Last-Translator: FULL NAME <EMAIL@ADDRESS>
Language-Team: Czech <https://hosted.weblate.org/projects/vesta-webtrees-custom-modules/vesta-shared-places/cs/>
Language: cs
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;
X-Generator: Weblate 4.5-dev
  (Poznámka: zároveň bylo vytvořeno %s sdílených míst vyšší úrovně)  (Poznámka: zároveň bylo vytvořeno sdílené místo vyšší úrovně) %s a osoby, které na něj/ni odkazují. ... a propadnout zpět na n nadřazených úrovní Modul poskytující podporu pro sdílená místa. Modul podporující sdílená místa jako GEDCOM objekty úrovně 0 na základě dodatku GEDCOM-L ke specifikaci GEDCOM 5.5.1. Sdílená místa mohou obsahovat např. zeměpisné souřadnice, poznámky a objekty médií. Modul zobrazuje všechna odpovídající místa přes rozšířenou záložku „Fakta a události“. V kombinaci s modulem Gov4Webtrees je možno jej využít i pro správu GOV-id. Název místa s částmi oddělenými čárkami se převede na hierarchii sdílených míst. Vytvoří se také chybějící sdílená místa vyšší úrovně. V souladu s dodatkem GEDCOM-L se na sdílená místa odkazuje pomocí XREF, stejně jako na sdílené poznámky atd.  Přidat %s do schránky výstřižků Přidat chybějící XREFy Doplňkově propojit sdílená místa skrze název Všechna fakta sdíleného místa Pozor! Automaticky rozbalit údaje sdíleného místa Vytvořit sdílené místo Vytvořit všechna chybějící sdílená místa a přidat chybějící XREFy Vytvořit chybějící sdílená místa z údajů nezávislých na rodokmenu a přidat chybějící XREFy Opravy údajů Zjišťování počtu odkazů (připojených osob/rodin) je náročné, když jsou sdílená místa propojená skrz jméno, a proto může při zobrazování seznamu sdílených míst způsobit zpoždění. Doporučuje se volit tuto volbu jen tehdy, když jsou sdílená místa propojená skrz XREF. Abyste mohli prohlížet hierarchii sdílených míst, povolte modul „Vesta mapa míst a předků“. Doplnit existující sdílená místa údaji nezávislými na rodokmenu Fakta pro záznamy sdílených míst GOV-Id pro typ lokality Vytvořit hierarchii sdílených míst Sdílené místo vyšší úrovně Přesto však můžete označit tuto volbu a propojit sdílená místa skrz jejich název. V tom případě se propojení vytvoří interně vyhledáním sdíleného místa se shodným názvem, bez ohledu na velikost písmen. Když je zvoleno, vztahy mezi sdílenými místy se modelují prostřednictvím explicitní hierarchie, kde sdílená místa mají XREF na sdílená místa vyšší úrovně, jak je popsáno ve specifikaci. Je-li zvolena tato možnost, pak se sdílená místa zobrazí jen pro následující fakta a události.  Jsou-li k dispozici údaje nezávislé na rodokmenu, přidají se zeměpisné souřadnice ze sekce webtrees „Geografická data“ a identifikátory GOV z modulu Gov4Wetrees. Když používáte hierarchická sdílená místa, tak místo s názvem "A, B, C" se mapuje na sdílené místo "A" se sdíleným místem vyšší úrovně, které se mapuje na "B, C". Důležitá poznámka: Konkrétně, hierarchická sdílená místa nemají názvy s čárkou oddělenými částmi. Konkrétně, přidají se zeměpisné souřadnice ze sekce webtrees "Geografická data" a identifikátory GOV z modulu Gov4Webtrees. Nyní se doporučuje používat XREF, jelikož to zlepšuje výkon a flexibilitu. Na přidání XREF je na panelu správy k dipozici funkce Opravy údajů.  K vyřešení tohoto problému se doporučuje spustit funkci Opravy údajů pro tento uživatelský modul. Důrazně se doporučuje nejprve zálohovat rodokmen. Důrazně se doporučuje přejít na hierarchická sdílená místa. Obvykle to bude nutné provést jen jednou, jako migraci, když se pomocí příslušných nastavení zapne používání hierarchických sdílených míst. Propojení sdílených míst s místy Poloha Odpovídající sdílená místa se určí stejně jako přes konfigurační volbu 'Dodatečně připojit sdílená místa přes název', včetně vyšších úrovní, jsou-li nastavené. Odpovídající sdílená místa se určí stejně jako přes konfigurační volbu 'Dodatečně připojit sdílená místa přes název'. Podle potřeby se vytvoří sdílená místa vyšší úrovně. Aby to fungovalo bez potenciálního vytváření duplicit, musíte v nastavení uživatele nastavit volbu 'Automaticky přijmout změny provedené tímto uživatelem' při nejmenším na dobu, kdy se bude provádět úkon opravy údajů. Podle potřeby se vytvoří sdílená místa. Aby to fungovalo bez potenciálního vytváření duplicit, musíte v nastavení uživatele nastavit volbu 'Automaticky přijmout změny provedené tímto uživatelem' při nejmenším na dobu, kdy se bude provádět úkon opravy údajů. Sdílená místa nejblíže nižší úrovně Poznamenejme, že první výskyt může patřit k přepínatelnému, aktuálně skrytému faktu nebo události (jako třeba událost blízkého příbuzného). Toto bude pravděpodobně v budoucích versích modulu vylepšeno. Poznamenejme, že toto také ovlivní způsob vytváření sdílených míst a jejich mapování na místa. Poznámka: Názvy míst se v průběhu času mohou měnit. Sdílenému místu můžete přidat více názvů a historické názvy označit vhodným časovým intervalem. Názvy míst se musí zadávat jako čárkami oddělený seznam, začínající nejmenším místem a končící názvem země. Např. "Kostelec, Jihomoravský, Česko". Názvy míst se musí zadávat jako prostý název místa (zde nezadávejte čárkami oddělený seznam). Soukromé Pohotová fakta sdílených míst Omezit na určitá fakta a události Podrobnosti vizte v %1$s. Sdílené místo Údaje sdíleného místa Hierarchie sdíleného místa Seznam sdílených míst Název sdíleného místa Název sdíleného místa (úplná hierarchie) Struktura sdíleného místa Shrnutí sdíleného místa Sdílená místa Seznam sdílených míst Zobrazit počty odkazů pro seznam sdílených míst Shrnutí Vytvořená sdílená místa, stejně jako existující sdílená místa, jsou přes XREF propojena k místům událostí. Poloha tohoto sdíleného místa není známa. Toto hledání nelze uskutečnit efektivně a může trvat delší čas, zejména když se zobrazuje a upravuje velký počet záznamů. Hledání pro tuto opravu údajů nenachází žádné záznamy, protože není nastavena konfigurační volba 'Použít hierarchická sdílená místa'. Sdílené místo %s už existuje. Sdílené místo %s je vytvořeno. Shrnutí ukazuje údaje sdíleného místa ve stejném formátu jako pro události s místem mapovaným na příslušné sdílené místo. Jsou k dispozici různé opravy údajů. Pro konversi existujících sdílených míst je k dispozici oprava údajů. Proto se tady název místa zobrazuje včetně úplné hierarchie. Proto tato oprava údajů umožňuje obejít se bez použití té konfigurační volby. Tato oprava údajů přidává XREFy a současně propojuje všechna místa událostí přímo s odpovídajícími sdíleným místy. Tato oprava údajů přidává na rodokmenu nezávislé údaje (spravované mimo GEDCOM) k existujícícm sdíleným místům. Tato oprava údajů vytváří chybějící sdílená místa, a to i když pro příslušné místo nejsou dostupné na rodokmenu nezávislé údaje (spravované mimo GEDCOM). Tato oprava údajů vytváří chybějící sdílená místa, jestliže pro příslušné místo jsou dostupné na rodokmenu nezávislé údaje (spravované mimo GEDCOM). Tato oprava údajů nic neupraví, protože tato volba není nastavená. Tato oprava údajů změní sdílená místa s čárkou oddělenými částmi na hierarchická sdílená místa (která jsou propojena se sdílenými místy vyšší úrovně skrze XREF). Toto je seznam GEDCOM faktů, které mohou uživatelé přidávat ke sdíleným místům. Tento seznam můžete upravit odstraněním nebo přidáním názvů faktů dle potřeby. Názvy faktů v tomto seznamu se nesmí zároveň nacházet v seznamu "Jedinečné fakty sdílených míst". Toto je seznam GEDCOM faktů, které mohou uživatelé přidávat ke sdíleným místům. Tento seznam můžete upravit odstraněním nebo přidáním názvů faktů dle potřeby. Názvy faktů v tomto seznamu se nesmí zároveň nacházet v seznamu "Jedinečné fakty sdílených míst".  Toto je seznam GEDCOM faktů, které mohou uživatelé přidat ke sdíleným místům právě jednou. Např. je-li v tomto seznamu NAME, uživatel nebude moci ke sdílenému místu přidat víc než jeden záznam NAME. Fakty v tomto seznamu se nesmí zároveň nacházet v seznamu "Všechny fakty sdílených míst". Toto vede při mapování míst na sdílená místa k nekonzistentnosti a obecně neodpovídá specifikaci pro sdílená místa (kterou předchozí verse tohoto uživatelského modulu nedodržovaly striktně). Toto sdílené místo neexistuje, nebo nemáte oprávnění k jeho zobrazení. Toto sdílené místo je smazané. Výmaz musí potvrdit moderátor. Toto sdílené místo je smazané. Doporučuje se výmaz revidovat a pak jej %1$s, nebo %2$s. Toto sdílené místo je upravené. Úpravu musí revidovat moderátor. Toto sdílené místo je upravené. Doporučuje se úpravu revidovat a pak ji %1$s, nebo %2$s. Tento rodokmen má sdílená místa s čárkou oddělenými částmi, zatímco je současně nastavena volba 'Použít hierarchická sdílená místa'. Typ hierarchického vztahu Typ polohy (lokality) Jedinečné fakty sdíleného místa Použít hierarchická sdílená místa Na modelování hierarchie použít samostatný tag '%1$s'. Zobrazit hierarchii sdílených míst Když je nastavena předchozí volba a nenajde se odpovídající sdílené místo, propadnout na n nadřazených míst (takže např. pro n=2 místo "A, B, C" se bude také shodovat se sdílenými místy, která se shodují s "B, C" a "C") Když není zvolené, tak se použije předchozí přístup, v němž se hierarchie naznačí s použitím názvů sdíleného místa s čárkou oddělenými částmi. administrativní zacyklená hierarchie sdíleného místa kulturní zeměpisný ne jiný náboženský sdílená místa ano ano, ale jen první výskyt sdíleného místa 