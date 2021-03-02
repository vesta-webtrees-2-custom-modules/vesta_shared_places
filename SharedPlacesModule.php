<?php

namespace Cissee\Webtrees\Module\SharedPlaces;

use Aura\Router\Route;
use Aura\Router\RouterContainer;
use Vesta\Hook\HookInterfaces\EmptyIndividualFactsTabExtender;
use Vesta\Hook\HookInterfaces\IndividualFactsTabExtenderInterface;
use Cissee\Webtrees\Module\SharedPlaces\HelpTexts;
use Cissee\WebtreesExt\AbstractModule;
use Cissee\WebtreesExt\Exceptions\SharedPlaceNotFoundException;
use Cissee\WebtreesExt\Factories\SharedPlaceFactory;
use Cissee\WebtreesExt\FactPlaceAdditions;
use Cissee\WebtreesExt\Functions\ExtendedFunctionsEditPlacHandler;
use Cissee\WebtreesExt\Functions\FunctionsEditPlacHandler;
use Cissee\WebtreesExt\Functions\FunctionsPrintExt;
use Cissee\WebtreesExt\Http\Controllers\ModulePlaceHierarchyInterface;
use Cissee\WebtreesExt\Http\Controllers\PlaceHierarchyLink;
use Cissee\WebtreesExt\Http\Controllers\PlaceHierarchyParticipant;
use Cissee\WebtreesExt\Http\Controllers\PlaceUrls;
use Cissee\WebtreesExt\Http\Controllers\PlaceWithinHierarchy;
use Cissee\WebtreesExt\Http\RequestHandlers\CreateSharedPlaceAction;
use Cissee\WebtreesExt\Http\RequestHandlers\CreateSharedPlaceModal;
use Cissee\WebtreesExt\Http\RequestHandlers\Select2Location;
use Cissee\WebtreesExt\Http\RequestHandlers\SharedPlacePage;
use Cissee\WebtreesExt\Module\ClippingsCartModule;
use Cissee\WebtreesExt\PlaceViaSharedPlace;
use Cissee\WebtreesExt\Requests;
use Cissee\WebtreesExt\Services\GedcomEditServiceExt;
use Cissee\WebtreesExt\Services\SearchServiceExt;
use Cissee\WebtreesExt\SharedPlace;
use Cissee\WebtreesExt\SharedPlacePreferences;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Functions\FunctionsPrint;
use Fisharebest\Webtrees\Functions\FunctionsPrintFacts;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\Middleware\AuthEditor;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleDataFixInterface;
use Fisharebest\Webtrees\Module\ModuleDataFixTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleInterface;
use Fisharebest\Webtrees\Module\ModuleListInterface;
use Fisharebest\Webtrees\Module\ModuleListTrait;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\PlaceLocation;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\DataFixService;
use Fisharebest\Webtrees\Services\GedcomEditService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\User;
use Fisharebest\Webtrees\View;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use stdClass;
use Throwable;
use Vesta\Hook\HookInterfaces\EmptyFunctionsPlace;
use Vesta\Hook\HookInterfaces\EmptyPrintFunctionsPlace;
use Vesta\Hook\HookInterfaces\FunctionsClippingsCartInterface;
use Vesta\Hook\HookInterfaces\FunctionsPlaceInterface;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Hook\HookInterfaces\GovIdEditControlsInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsUtils;
use Vesta\Hook\HookInterfaces\PrintFunctionsPlaceInterface;
use Vesta\Model\GedcomDateInterval;
use Vesta\Model\GenericViewElement;
use Vesta\Model\GovReference;
use Vesta\Model\LocReference;
use Vesta\Model\MapCoordinates;
use Vesta\Model\PlaceStructure;
use Vesta\Model\Trace;
use Vesta\VestaModuleTrait;
use function app;
use function redirect;
use function response;
use function route;
use function str_contains;
use function str_starts_with;
use function view;

//cannot use original AbstractModule because we override setName
class SharedPlacesModule extends AbstractModule implements 
  ModuleCustomInterface, 
  ModuleListInterface, 
  ModuleConfigInterface, 
  ModuleGlobalInterface, 
  ModuleDataFixInterface,
  IndividualFactsTabExtenderInterface, 
  FunctionsPlaceInterface,
  PrintFunctionsPlaceInterface,
  FunctionsClippingsCartInterface,
  PlaceHierarchyParticipant {

  use ModuleCustomTrait, ModuleListTrait, ModuleConfigTrait, ModuleGlobalTrait, VestaModuleTrait, ModuleDataFixTrait {
    VestaModuleTrait::customTranslations insteadof ModuleCustomTrait;
    VestaModuleTrait::customModuleLatestVersion insteadof ModuleCustomTrait;
    VestaModuleTrait::getAssetAction insteadof ModuleCustomTrait;
    VestaModuleTrait::assetUrl insteadof ModuleCustomTrait;
    
    VestaModuleTrait::getConfigLink insteadof ModuleConfigTrait;
  }

  use SharedPlacesModuleTrait;
  use EmptyIndividualFactsTabExtender;
  use EmptyFunctionsPlace;
  use EmptyPrintFunctionsPlace;

  protected $module_service;  
  
  public function __construct(
          ModuleService $module_service) {
    
    $this->module_service = $module_service;
  }

  public function customModuleAuthorName(): string {
    return 'Richard Cissée';
  }

  public function customModuleSupportUrl(): string {
    return 'https://cissee.de';
  }
  
  public function customModuleVersion(): string {
    return file_get_contents(__DIR__ . '/latest-version.txt');
  }

  public function customModuleLatestVersionUrl(): string {
    return 'https://raw.githubusercontent.com/vesta-webtrees-2-custom-modules/vesta_shared_places/master/latest-version.txt';
  }

  public function resourcesFolder(): string {
    return __DIR__ . '/resources/';
  }  
  
  public function listTitle(): string {
    return $this->getListTitle(I18N::translate("Shared places"));
  }

  public function listMenuClass(): string {
    return 'menu-list-plac';
  }
    
  public function getListAction(ServerRequestInterface $request): ResponseInterface {
    //'tree' is handled specifically in Router.php
    $tree = $request->getAttribute('tree');
    assert($tree instanceof Tree);
    
    $user = $request->getAttribute('user');
    Auth::checkComponentAccess($this, ModuleListInterface::class, $tree, $user);
    
    $link = null;
    $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
    $locationsToFix = $this->locationsToFixWrtHierarchy($tree);
    $hasLocationsToFix = ($locationsToFix->count() > 0);
    
    if ($useHierarchy && !$hasLocationsToFix) {
      //link to place hierarchy
      $module = app(ModuleService::class)
            ->findByComponent(ModulePlaceHierarchyInterface::class, $tree, Auth::user())
            ->first(static function (ModuleInterface $module): bool {
                return $module instanceof ModulePlaceHierarchyInterface;
            });
        
      if ($module instanceof ModulePlaceHierarchyInterface) {
          $url = $module->listUrl($tree, [
                'place_id'     => 0,
                'tree'         => $tree->name(),
                'sharedPlaces' => 1,
          ]);
          $link = new PlaceHierarchyLink(I18N::translate("View Shared places hierarchy"), null, $url);
      } else {
          $link = new PlaceHierarchyLink(I18N::translate("Enable the Vesta Places and Pedigree map module to view the shared places hierarchy."), null, null);
      }
    }
    
    $controller = new SharedPlacesListController($this, $hasLocationsToFix, $link);

    $showLinkCounts = boolval($this->getPreference('LINK_COUNTS', '0'));
    return $controller->sharedPlacesList($tree, $showLinkCounts);
  }
  
  /**
   * Bootstrap the module
   */
  public function onBoot(): void {
      //replace to handle subtags of PLAC
      app()->instance(GedcomEditService::class, new GedcomEditServiceExt());

      //explicitly register in order to re-use in views where we cannot pass via variable
      //(e.g. FunctionsEditLoc via replaced edit-fact.phtml)
      app()->instance(SharedPlacesModule::class, $this); //do not use bind()! for some reason leads to 'Illegal offset type in isset or empty'
    
      //_LOC edit control on events
      app()->instance(FunctionsEditPlacHandler::class, new ExtendedFunctionsEditPlacHandler());
      
      $cache = Registry::cache()->array();
      $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
      $useIndirectLinks = boolval($this->getPreference('INDIRECT_LINKS', '1'));
      
      $addfactsStr = $this->getPreference('_LOC_FACTS_ADD', 'NAME,_LOC:TYPE,NOTE,SHARED_NOTE,SOUR,_LOC:_LOC');
      $addfacts = FunctionsPrintExt::adjust(preg_split("/[, ]+/", $addfactsStr, -1, PREG_SPLIT_NO_EMPTY));
      
      $uniquefactsStr = $this->getPreference('_LOC_FACTS_UNIQUE', 'MAP,_GOV');
      $uniquefacts = FunctionsPrintExt::adjust(preg_split("/[, ]+/", $uniquefactsStr, -1, PREG_SPLIT_NO_EMPTY));
      
      $requiredfactsStr = $this->getPreference('_LOC_FACTS_REQUIRED', '');
      $requiredfacts = FunctionsPrintExt::adjust(preg_split("/[, ]+/", $requiredfactsStr, -1, PREG_SPLIT_NO_EMPTY));
            
      $quickfactsStr = $this->getPreference('_LOC_FACTS_QUICK', 'NAME,_LOC:_LOC,MAP,NOTE,SHARED_NOTE,_GOV');
      $quickfacts = FunctionsPrintExt::adjust(preg_split("/[, ]+/", $quickfactsStr, -1, PREG_SPLIT_NO_EMPTY));      
      
      $preferences = new SharedPlacePreferences(
              $useHierarchy, 
              $useIndirectLinks,
              $addfacts,
              $uniquefacts,
              $requiredfacts,
              $quickfacts);
      
      $sharedPlaceFactory = new SharedPlaceFactory($cache, $preferences);
      Registry::locationFactory($sharedPlaceFactory);
      
      //define our 'pretty' routes
      //note: potentially problematic in case of name clashes; 
      //webtrees isn't interested in solving this properly, see
      //https://www.webtrees.net/index.php/en/forum/2-open-discussion/33687-pretty-urls-in-2-x

      $router_container = app(RouterContainer::class);
      assert($router_container instanceof RouterContainer);
      $router = $router_container->getMap();
      
      //(cf WebRoutes.php "Visitor routes with a tree")
      //note: this has the side effect of handling pricacy properly (Issue #9)
      $router->get(SharedPlacePage::class, '/tree/{tree}/sharedPlace/{xref}{/slug}', SharedPlacePage::class);    
    
      // Replace an existing view with our own version.
      // (media management via list module)
      View::registerCustomView('::modules/media-list/page', $this->name() . '::modules/media-list/page');
      
      // Register a view under the main namespace (referred to from modules/media-list/page)
      View::registerCustomView('::icons/shared-place', $this->name() . '::icons/shared-place');
      
      // Replace an existing view with our own version.
      // (record icons e.g. for clippings cart)
      View::registerCustomView('::icons/record', $this->name() . '::icons/record');
      
      // Replace an existing view with our own version.
      // (media management via admin)
      View::registerCustomView('::media-page', $this->name() . '::media-page');

      // Replace an existing view with our own version.
      View::registerCustomView('::note-page', $this->name() . '::note-page');

      // Replace an existing view with our own version.
      // (adjustments for _LOC.NAME, _LOC.MAP, _LOC._GOV, _LOC.TYPE, , _LOC._LOC)
      View::registerCustomView('::edit/add-fact', $this->name() . '::edit/add-fact');
      View::registerCustomView('::edit/edit-fact', $this->name() . '::edit/edit-fact');
      
      //plus adjustment
      View::registerCustomView('::edit/fact-location-edit', $this->name() . '::edit/fact-location-edit');
      
      // (adjustments for _LOC under PLAC)
      View::registerCustomView('::edit/new-individual', $this->name() . '::edit/new-individual');      
      // Register a view under the main namespace
      View::registerCustomView('::edit/plac', $this->name() . '::edit/plac');
      
      // Register a view under the main namespace (referred to from replaced media-page)
      View::registerCustomView('::lists/shared-places-table', $this->name() . '::lists/shared-places-table');
      
      
      $createSharedPlaceModal = new CreateSharedPlaceModal($this);
      
      $router->get(CreateSharedPlaceModal::class, '/tree/{tree}/create-location', $createSharedPlaceModal)
              ->extras(['middleware' => [AuthEditor::class]]);
      
      $router->post(CreateSharedPlaceAction::class, '/tree/{tree}/create-location', CreateSharedPlaceAction::class)
              ->extras(['middleware' => [AuthEditor::class]]);
      
      //TODO: cleanup - remove
      //for GenericPlaceHierarchyController
      View::registerCustomView('::modules/generic-place-hierarchy-shared-places/place-hierarchy', $this->name() . '::modules/generic-place-hierarchy-shared-places/place-hierarchy');
      View::registerCustomView('::modules/generic-place-hierarchy-shared-places/list', $this->name() . '::modules/generic-place-hierarchy-shared-places/list');
      View::registerCustomView('::modules/generic-place-hierarchy-shared-places/page', $this->name() . '::modules/generic-place-hierarchy-shared-places/page');
      View::registerCustomView('::modules/generic-place-hierarchy-shared-places/sidebar', $this->name() . '::modules/generic-place-hierarchy-shared-places/sidebar');
      
      ////////////////////////////////////////////////////////////////////////////
      // Location support, some of this could be in webtrees itself
      
      View::registerCustomView('::components/select-location', $this->name() . '::components/select-location');
      View::registerCustomView('::selects/location', $this->name() . '::selects/location');
      
      $router->post(Select2Location::class, '/tree/{tree}/select2-location', Select2Location::class);
      
      ////////////////////////////////////////////////////////////////////////////
      /* I18N: translate just like 'Shared Place' for consistency */I18N::translate('Location');
      
      //added via GedcomTag.php
      /* I18N: Gedcom tag _LOC:_LOC */I18N::translate('Higher-level shared place');
      /* I18N: Gedcom tag _LOC:_LOC:TYPE */I18N::translate('Type of hierarchical relationship');
      /* I18N: Gedcom tag _LOC:TYPE */I18N::translate('Type of location');
      /* I18N: Gedcom tag _LOC:TYPE:_GOVTYPE */I18N::translate('GOV-Id for type of location');
      
      $this->flashWhatsNew('\Cissee\Webtrees\Module\SharedPlaces\WhatsNew', 3);
  }
  
  public function getHelpAction(ServerRequestInterface $request): ResponseInterface {
    $topic = Requests::getString($request, 'topic');
    return response(HelpTexts::helpText($topic));
  }
  
  //no longer required - css is static now
  //public function assetsViaViews(): array {
  //  return [
  //      'css/webtrees.css' => 'css/webtrees',
  //      'css/minimal.css' => 'css/minimal'];
  //}
  
  //css for icons/shared-place
  public function headContent(): string {
    //easier to serve this globally, even if not strictly required on each page
    //(we need the css in modified webtrees views, e.g. for media management)
    
    //align with current theme (supporting the default webtrees themes, and specific custom themes)
    $themeName = Session::get('theme');
    if ('minimal' !== $themeName) {
      if ('fab' === $themeName) {
        //fab also uses font awesome icons
        $themeName = 'minimal';
      } else if ('_myartjaub_ruraltheme_' === $themeName) {
        //and the custom 'rural' theme
        $themeName = 'minimal';
      } else if ('_jc-theme-justlight_' === $themeName) {
        //and the custom 'JustLight' theme
        $themeName = 'minimal';
      } else {
        //default
        $themeName = 'webtrees';
      }      
    }
    
    $pre = '<link href="' . $this->assetUrl('css/'.$themeName.'.css') . '" type="text/css" rel="stylesheet" />';
		return $pre;
  } 
  
  public function hFactsTabRequiresModalVesta(Tree $tree): ?string {
    //required via CreateSharedPlaceAction
    $additionalControls = GovIdEditControlsUtils::accessibleModules($tree, Auth::user())
            ->map(function (GovIdEditControlsInterface $module) {
              return $module->govIdEditControlSelect2ScriptSnippet();
            })
            ->toArray();
            
    return implode($additionalControls);        
  }
  
  public function hFactsTabGetAdditionalEditControls(
          Fact $fact): GenericViewElement {
    
    if (!$fact->canEdit()) {
      //not editable
      return new GenericViewElement('', '');
    }
    
    if ($fact->attribute('PLAC') === '') {
      //no PLAC, doesn't make sense to edit here
      return new GenericViewElement('', '');
    }
    
    $useIndirectLinks = boolval($this->getPreference('INDIRECT_LINKS', '1'));
    
    if (!$useIndirectLinks) {
      //doesn't make sense to edit here
      //(fact place must be linked explicitly to shared place anyway;
      //we provide this functionality in fact place editor itself instead in this case)
      return new GenericViewElement('', '');
    }
    
    //ok to edit - does a shared place with this name already exist? Or does the PLAC have an explicit _LOC link?
    $ps = PlaceStructure::fromFact($fact);
    if ($ps !== null) {
      $sharedPlace = $this->plac2sharedPlace($ps);    
    }
    
    if ($sharedPlace !== null) {
      //already exists
      return new GenericViewElement('', '');
    }
    
    //we're using ajax-modal-vesta here 
    //because there may be modules with additional edit controls requiring this container
    //
    //this is somewhat hacky because we assume at the same time that these modules
    //have initialized the container properly via hFactsTabGetOutputBeforeTab,
    //which is strictly not enforced (in particular the modules aren't aware of the context of the edit control)
    $html = view($this->name() . '::edit/icon-fact-create-shared-place', ['fact' => $fact, 'moduleName' => $this->name()]);
    
    return new GenericViewElement($html, '');
  }
  
  protected static $seenSharedPlaces = [];

  public function getLinkForSharedPlace(SharedPlace $sharedPlace): string {
    return $this->linkIcon(
            $this->name() . '::icons/shared-place', 
            I18N::translate('Shared place'), 
            $sharedPlace->url());
  }
  
  protected function getHtmlForSharedPlaceData(PlaceStructure $place) {
    $html1 = '';
    $html = '';
    $sharedPlace = $this->plac2sharedPlace($place);
    if ($sharedPlace === null) {
      return array($html1, $html);
    }
    
    //restrict to specific events?
    $restricted = $this->getPreference('RESTRICTED', '0');

    if ($restricted) {
      $restricted_indi = $this->getPreference('RESTRICTED_INDI', 'BIRT,MARR,OCCU,RESI,DEAT');
      $restrictedTo = preg_split("/[, ;:]+/", $restricted_indi, -1, PREG_SPLIT_NO_EMPTY);
      if (!in_array($place->getEventType(), $restrictedTo, true)) {

        $restricted_fam = $this->getPreference('RESTRICTED_FAM', 'MARR');
        $restrictedTo = preg_split("/[, ;:]+/", $restricted_fam, -1, PREG_SPLIT_NO_EMPTY);
        if (!in_array($place->getEventType(), $restrictedTo, true)) {
          return array($this->getLinkForSharedPlace($sharedPlace), '');
        }
      }
    }
    
    //add link
    $html1 .= $this->linkIcon(
            $this->name() . '::icons/shared-place', 
            I18N::translate('Shared place'), 
            $sharedPlace->url());
            
    //add name if different from PLAC name
    $nameAt = $sharedPlace->primaryPlaceAt($place->getEventDateInterval())->gedcomName();
    if ($nameAt !== $place->getGedcomName()) {
      $html .= '<div class="indent">';
      $html .= $nameAt;
      $html .= '</div>';
    }
    
    //add all (level 1) notes
    if (preg_match('/1 NOTE (.*)/', $sharedPlace->gedcom(), $match)) {
      //note may be restricted - in which case, do not add wrapper
      //(and ultimately perhaps do not add entire 'shared place data', in case there is nothing else to display)
      $note = FunctionsPrint::printFactNotes($place->getTree(), $sharedPlace->gedcom(), 1);
      if ($note !== '') {
        $html .= '<div class="indent">';
        $html .= $note;
        //$html .= '<br>';
        $html .= '</div>';
      }
    }
    //add all (level 1) media
    if (preg_match_all("/1 OBJE @(.*)@/", $sharedPlace->gedcom(), $match)) {
      ob_start();
      FunctionsPrintFacts::printMediaLinks($place->getTree(), $sharedPlace->gedcom(), 1);
      $media = ob_get_clean();
      if ($media !== '') {
        $html .= '<div class="indent">';
        $html .= $media;
        $html .= '<br class="media-separator" style="clear:both;">'; //otherwise layout issues wrt following elements, TODO handle differently!
        $html .= '</div>';
      }
    }

    //add all (level 1) sources
    if (preg_match_all("/1 SOUR @(.*)@/", $sharedPlace->gedcom(), $match)) {
      $sources = FunctionsPrintFacts::printFactSources($place->getTree(), $sharedPlace->gedcom(), 1);
      if ($sources !== '') {
        $html .= '<div class="indent">';
        $html .= $sources;
        $html .= '<br class="media-separator" style="clear:both;">'; //otherwise layout issues wrt following elements, TODO handle differently!
        $html .= '</div>';
      }
    }
      
    if ($html !== '') {
      //wrap in order to make expandable/collapsible
      $data = '<br/>';

      $expandSetting = $this->getPreference('EXPAND', '1');
      if ($expandSetting == '0') {
        $expanded = false;
      } else if ($expandSetting == '1') {
        if (in_array($sharedPlace->xref(), SharedPlacesModule::$seenSharedPlaces)) {
          $expanded = false;
        } else {
          $expanded = true;
        }
        SharedPlacesModule::$seenSharedPlaces[] = $sharedPlace->xref();
      } else {
        $expanded = true;
      }

      $id = 'collapse-' . Uuid::uuid4()->toString();
            
      $data .= '<a href="#' . e($id) . '" role="button" data-toggle="collapse" aria-controls="' . e($id) . '" aria-expanded="' . ($expanded ? 'true' : 'false') . '">' .
            view('icons/expand') .
            view('icons/collapse') .
            '</a> ';
      
      $data .= '<span class="label"><a href="' . $sharedPlace->url() . '">' . I18N::translate('Shared place data') . '</a></span>';
      $data .= '<div id="' . e($id) . '" class="shared_place_data collapse ' . ($expanded ? 'show' : '') . '">' .
            $html .
            '</div>';
      $data .= '</div>';


      $html = $data;
    } //else no shared place, or shared place without contents
    return array($html1, $html);
  }

  public function linkIcon($view, $title, $url) {
    return '<a href="' . $url . '" rel="nofollow" title="' . $title . '">' .
            view($view) .
            '<span class="sr-only">' . $title . '</span>' .
            '</a>';
  }
  
  ////////////////////////////////////////////////////////////////////////////////
  
  public function placename2sharedPlaceImpl(
          string $placeGedcomName, 
          Tree $tree): ?SharedPlace {
    
    return $this->placename2sharedPlacesImpl($placeGedcomName, $tree)->first();
  }
  
  public function placename2sharedPlacesImpl(
          string $placeGedcomName, 
          Tree $tree): Collection {
    
    if ($placeGedcomName === '') {
      return new Collection();
    }
    
    $searchService = app(SearchServiceExt::class);    
    return $searchService->searchLocationsInPlace(new Place($placeGedcomName, $tree));
  }
  
  protected function placename2sharedPlace(
          string $placeGedcomName, 
          Tree $tree): ?SharedPlace {
    
    if ($placeGedcomName === '') {
      return null;
    }
    
    $parentLevels = intval($this->getPreference('INDIRECT_LINKS_PARENT_LEVELS', 0));
    return $this->placename2sharedPlacePL($placeGedcomName, $tree, $parentLevels);
  }
  
  protected function placename2sharedPlacePL(
          string $placeGedcomName, 
          Tree $tree,
          int $parentLevels): ?SharedPlace {
    
    $match = $this->placename2sharedPlaceImpl($placeGedcomName, $tree);
    
    if (($match === null) && ($parentLevels > 0)) {
      $parts = SharedPlace::placeNameParts($placeGedcomName);
      $tail = SharedPlace::placeNamePartsTail($parts);
      return $this->placename2sharedPlacePL($tail, $tree, $parentLevels-1);
    }
    
    return $match;    
  }
  
  protected function plac2sharedPlace(PlaceStructure $ps): ?SharedPlace {
    $loc = $ps->getLoc();
    if ($loc !== null) {
      return Registry::gedcomRecordFactory()->make($loc, $ps->getTree());
    }
    
    $indirect = boolval($this->getPreference('INDIRECT_LINKS', '1'));
    if ($indirect) {
      return $this->placename2sharedPlace($ps->getGedcomName(), $ps->getTree());
    }

    return null;
  }
 
  public function plac2loc(PlaceStructure $ps): ?LocReference {
    $loc = $ps->getLoc();
    if ($loc !== null) {
      $trace = new Trace('shared place via Shared Places module (gedcom _LOC tag)');
      return new LocReference($loc, $ps->getTree(), $trace, $ps->getLevel());
    }
    
    $indirect = boolval($this->getPreference('INDIRECT_LINKS', '1'));
    if ($indirect) {
      $sharedPlace = $this->placename2sharedPlace($ps->getGedcomName(), $ps->getTree());
      if ($sharedPlace !== null) {
        $trace = new Trace('shared place via Shared Places module (mapping via place name)');
        return new LocReference($sharedPlace->xref(), $sharedPlace->tree(), $trace, $ps->getLevel());
      }
    }

    return null;
  }
  
  public function loc2gov(LocReference $loc): ?GovReference {
    $sharedPlace = Registry::gedcomRecordFactory()->make($loc->getXref(), $loc->getTree());
    
    if (($sharedPlace !== null) && ($sharedPlace instanceof SharedPlace)) {
      $gov = $sharedPlace->getGov();
      if ($gov !== null) {
        $trace = $loc->getTrace();
        $trace->add('GOV-Id via Shared Places module (gedcom _GOV tag)');
        return new GovReference($gov, $trace, $loc->getLevel());
      }
    }
    
    return null;
  }
  
  public function gov2loc(GovReference $gov, Tree $tree): ?LocReference {
    $searchService = app(SearchServiceExt::class);
    $sharedPlaces = $searchService->searchLocationsEOL(array($tree), array("1 _GOV " . $gov->getId()));
    foreach ($sharedPlaces as $sharedPlace) {
      //first match wins
      $trace = $gov->getTrace();
      $trace->add('Location via Shared Places module');
      return new LocReference($sharedPlace->xref(), $tree, $trace, $gov->getLevel());
    }
    
    return null;
  }
  
  public function loc2map(LocReference $loc): ?MapCoordinates {
    $sharedPlace = Registry::gedcomRecordFactory()->make($loc->getXref(), $loc->getTree());
    
    if ($sharedPlace !== null) {
      $lati = $sharedPlace->getLati();
      $long = $sharedPlace->getLong();

      if (($lati !== null) && ($long !== null)) {
        $trace = $loc->getTrace();
        $trace->add('map coordinates via Shared Places module (gedcom MAP tag)');
        return new MapCoordinates($lati, $long, $trace);
      }
    }
    
    return null;
  }
  
  public function loc2plac(LocReference $loc): ?PlaceStructure {
    $sharedPlace = Registry::gedcomRecordFactory()->make($loc->getXref(), $loc->getTree());
    
    if ($sharedPlace !== null) {
      if (!empty($sharedPlace->namesNN())) {
        $ps = PlaceStructure::fromNameAndLoc($sharedPlace->primaryPlace()->gedcomName(), $sharedPlace->xref(), $sharedPlace->tree(), $loc->getLevel(), $sharedPlace);
        if ($ps !== null) {
          return $ps;
        }
      }  
    }
    
    return null;
  }
  
  public function loc2linkIcon(LocReference $loc): ?string {
    $sharedPlace = Registry::gedcomRecordFactory()->make($loc->getXref(), $loc->getTree());
    
    if ($sharedPlace !== null) {
      return $this->getLinkForSharedPlace($sharedPlace);
    }
    
    return null;
  }
  
  public function locPloc(LocReference $locReference, GedcomDateInterval $dateInterval, Collection $typesOfLocation, int $maxLevels = PHP_INT_MAX): Collection {
    $sharedPlace = Registry::gedcomRecordFactory()->make($locReference->getXref(), $locReference->getTree());
    
    $currentLevel = 0;
    $trace = $locReference->getTrace();
    $trace->add('Shared Place via Shared Places module (hierarchy)');
    $ret = new Collection();
    if ($sharedPlace !== null) {      
      foreach ($sharedPlace->getParents() as $parent) {
        //TODO check type of location via _GOVTYPE
        //TODO check dateInterval
        //TODO use maxLevels for transitive parents
        $ret->add(new LocReference($parent->xref(), $parent->tree(), $trace, $currentLevel));
      }
    }
    
    return $ret;
  }
  
  public function factPlaceAdditions(PlaceStructure $place): ?FactPlaceAdditions {
    //would be cleaner to use plac2loc here - in practice same result
    $htmls = $this->getHtmlForSharedPlaceData($place);
    return new FactPlaceAdditions(
            GenericViewElement::create($htmls[0]), 
            GenericViewElement::createEmpty(), 
            GenericViewElement::create($htmls[1]));
  }
  
  ////////////////////////////////////////////////////////////////////////////////
  //FunctionsClippingsCartInterface
    
  public function getDirectLinkTypes(): Collection {
    return new Collection(["_LOC"]);
  }
  
  public function getIndirectLinks(GedcomRecord $record): Collection {
    $ret = new Collection();
    
    $indirect = boolval($this->getPreference('INDIRECT_LINKS', '1'));
    if ($indirect) {
      $places = $record->getAllEventPlaces([]);
      foreach ($places as $place) {
        $sharedPlace = $this->placename2sharedPlace($place->gedcomName(), $record->tree());
        if ($sharedPlace != null) {
          $ret->push($sharedPlace->xref());
        }
      }
    }    
    
    return $ret;
  }
  
  public function getTransitiveLinks(GedcomRecord $record): Collection {
    $ret = new Collection();
    
    $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
    if ($useHierarchy) {
      if ($record instanceof SharedPlace) {
        //safer wrt loops (than to use getTransitiveLinks recursively)
        $queue = new Collection();        
        $queue->prepend($record);
        
        while ($queue->count() > 0) {
          $current = $queue->pop();
          $ret->add($current);
          foreach ($current->getParents() as $parent) {
            if (!$ret->contains($parent)) {
              $queue->prepend($parent);
            }
          }
        }
      }
    }    
    
    return $ret->map(function (GedcomRecord $record): string {
              return $record->xref();
            });
  }
  
  public function getAddToClippingsCartRoute(Route $route, Tree $tree): ?string {
    if ($route->name === SharedPlacePage::class) {
      $xref = $route->attributes['xref'];
      assert(is_string($xref));

      $add_route = route('module', [
          'module' => $this->name(),
          'action' => 'AddToClippingsCart',
          'xref'   => $xref,
          'tree'    => $tree->name(),
      ]);

      return $add_route;
    }
    
    return null;
  }
  
  public function getAddToClippingsCartAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $xref = $request->getQueryParams()['xref'];

        $sharedPlace = Registry::locationFactory()->make($xref, $tree);

        if ($sharedPlace === null) {
            throw new SharedPlaceNotFoundException();
        }

        $options = $this->clippingsCartOptions($sharedPlace);

        $title = I18N::translate('Add %s to the clippings cart', $sharedPlace->fullName());

        return $this->viewResponse('modules/clippings/add-options', [
            'options' => $options,
            'default' => key($options),
            'record'  => $sharedPlace,
            'title'   => $title,
            'tree'    => $tree,
        ]);
    }

    protected function clippingsCartOptions(SharedPlace $sharedPlace): array
    {
        $name = strip_tags($sharedPlace->fullName());
        
        return [
            'only'   => strip_tags($sharedPlace->fullName()),
            'linked' => I18N::translate('%s and the individuals that reference it.', $name),
        ];
    }

    public function postAddToClippingsCartAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $params = (array) $request->getParsedBody();

        $xref   = $params['xref'];
        $option = $params['option'];
 
        $sharedPlace = Registry::locationFactory()->make($xref, $tree);

        if ($sharedPlace === null) {
            throw new SharedPlaceNotFoundException();
        }

        $target = app()
            ->make(ModuleService::class)
            ->findByComponent(ClippingsCartModule::class, $tree, Auth::user())
            ->first();
        
        if ($target !== null) {
          $target->addRecordToCart($sharedPlace);
          
          if ($option === 'linked') {
              foreach ($sharedPlace->linkedIndividuals('_LOC') as $individual) {
                  $target->addRecordToCart($individual);
              }
              foreach ($sharedPlace->linkedFamilies('_LOC') as $family) {
                  $target->addRecordToCart($family);
              }
          }
        }

        return redirect($sharedPlace->url());
    }
  
  ////////////////////////////////////////////////////////////////////////////////
  //PlaceHierarchyParticipant
  
  public function participates(Tree $tree): bool {
    $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
    if (!$useHierarchy) {
      return false;
    }
    
    $locationsToFix = $this->locationsToFixWrtHierarchy($tree);
    return ($locationsToFix->count() === 0);
  }
  
  public function filterLabel(): string {
    return I18N::translate('shared places');
  }
  
  public function filterParameterName(): string {
    return 'sharedPlaces';
  }
  
  public function findPlace(
          int $id, 
          Tree $tree, 
          PlaceUrls $urls,
          bool $asAdditionalParticipant = false): PlaceWithinHierarchy {
    
    $actual = Place::find($id, $tree);
    
    //find matching shared places
    $sharedPlaces = $this->placename2sharedPlacesImpl($actual->gedcomName(), $actual->tree());    
    $searchService = app(SearchServiceExt::class);
    return new PlaceViaSharedPlace($actual, $asAdditionalParticipant, $urls, $sharedPlaces, $this, $searchService);
  }
  
  public function createNonMatchingPlace(Place $actual, PlaceUrls $urls) {
    //there are no matching shared places!
    $searchService = app(SearchServiceExt::class);
    return new PlaceViaSharedPlace($actual, false, $urls, new Collection(), $this, $searchService);
  }
  
  ////////////////////////////////////////////////////////////////////////////////
  //data fix
  //impl follows FixSearchAndReplace for regexing
    
  public function fixOptions(Tree $tree): string {
      
    $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
    
    //we need immediate accepts in order to avoid potential duplicates when creating new shared places!
    $autoAcceptEdits = (Auth::user()->getPreference(User::PREF_AUTO_ACCEPT_EDITS) === '1');
            
    return view($this->name() . '::data-fix-options', [
        'useHierarchy' => $useHierarchy,
        'autoAcceptEdits' => $autoAcceptEdits]);
  }
    
  //easier wrt start/end params handling to use method from trait!
  //override by record type instead!
  /*
  public function recordsToFix(Tree $tree, array $params): Collection {
    if (!array_key_exists('mode', $params)) {
      return new Collection();
    }
    
    if ($params['mode'] === 'hierarchicalize') {
      return $this->recordsToFixWrtHierarchy($tree);
    }
        
    if ($params['mode'] === 'enhance') {
      return $this->recordsToFixWrtEnhance($tree);
    }
    
    if ($params['mode'] === 'xrefs') {
      return $this->recordsToFixWrtXrefs($tree);
    }
    
    if ($params['mode'] === 'create1') {
      return $this->recordsToFixWrtXrefs($tree);
    }
    
    if ($params['mode'] === 'create2') {
      return $this->recordsToFixWrtXrefs($tree);
    }
    
    return new Collection();
  }
  */
  
  protected function locationsToFix(Tree $tree, array $params): ?Collection {
    if (!array_key_exists('mode', $params)) {
      return null;
    }
    
    if ($params['mode'] === 'hierarchicalize') {
      return $this->locationsToFixWrtHierarchy($tree, $params);
    }
        
    if ($params['mode'] === 'enhance') {
      return $this->locationsToFixWrtEnhance($tree, $params);
    }
        
    return null;
  }
    
  protected function locationsToFixWrtHierarchy(Tree $tree, array $params = []): Collection {
    $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
    
    if (!$useHierarchy) {
      return new Collection();
    }
    
    $query = DB::table('other')
        ->where('o_file', '=', $tree->id())
        ->where('o_type', '=', Location::RECORD_TYPE);

    $this->recordQuery($query, 'o_gedcom', '[\n]1 NAME[^\n]*,');

    return $query->pluck('o_id');
  }
  
  protected function locationsToFixWrtEnhance(Tree $tree, array $params = []): Collection {
    
    $query = DB::table('other')
        ->where('o_file', '=', $tree->id())
        ->where('o_type', '=', Location::RECORD_TYPE);

    $regexp = $this->regexpOp(true);
    
    if ($regexp !== null) {
      $self = $this;
      $query->where(static function (Builder $q) use ($regexp, $self, $tree): void {
                $q->where('o_gedcom', $regexp, $self->subst('[\n]1 MAP'));
                if (sizeof($self->getPlac2GovSupporters($tree)) > 0) {
                  $q->orWhere('o_gedcom', $regexp, $self->subst('[\n]1 _GOV'));
                }                
            });
    }    

    if (isset($params['start'], $params['end'])) {
        $query->whereBetween('o_id', [$params['start'], $params['end']]);
    }
    
    return $query->pluck('o_id');
  }
  
  protected function individualsToFix(Tree $tree, array $params): ?Collection {
    if (!array_key_exists('mode', $params)) {
      return new Collection();
    }
    
    if ($params['mode'] === 'xrefs') {
      return $this->individualsToFixWrtXrefs($tree, $params);
    }
    
    if ($params['mode'] === 'create1') {
      return $this->individualsToFixWrtXrefs($tree, $params);
    }
    
    if ($params['mode'] === 'create2') {
      return $this->individualsToFixWrtXrefs($tree, $params);
    }
    
    return null;
  }
  
  protected function individualsToFixWrtXrefs(Tree $tree, array $params = []): Collection {

    //count
    //\n2 PLAC
    //vs
    //\n3 _LOC
    //see https://stackoverflow.com/questions/5427467/mysql-count-instances-of-substring-then-order-by
    
    $query = DB::table('individuals')
            ->where('i_file', '=', $tree->id())
            ->whereRaw('((CHAR_LENGTH(i_gedcom) - CHAR_LENGTH(REPLACE(i_gedcom, \'\n2 PLAC\', \'\'))) / 7) > ((CHAR_LENGTH(i_gedcom) - CHAR_LENGTH(REPLACE(i_gedcom, \'\n3 _LOC\', \'\'))) / 7)');

    if (isset($params['start'], $params['end'])) {
        $query->whereBetween('i_id', [$params['start'], $params['end']]);
    }
    
    return $query->pluck('i_id');
  }
  
  protected function familiesToFix(Tree $tree, array $params): ?Collection {
    if (!array_key_exists('mode', $params)) {
      return new Collection();
    }
    
    if ($params['mode'] === 'xrefs') {
      return $this->familiesToFixWrtXrefs($tree, $params);
    }
    
    if ($params['mode'] === 'create1') {
      return $this->familiesToFixWrtXrefs($tree, $params);
    }
    
    if ($params['mode'] === 'create2') {
      return $this->familiesToFixWrtXrefs($tree, $params);
    }
    
    return null;
  }
  
  protected function familiesToFixWrtXrefs(Tree $tree, array $params = []): Collection {

    //count
    //\n2 PLAC
    //vs
    //\n3 _LOC
    //see https://stackoverflow.com/questions/5427467/mysql-count-instances-of-substring-then-order-by
    
    $query = DB::table('families')
            ->where('f_file', '=', $tree->id())
            ->whereRaw('((CHAR_LENGTH(f_gedcom) - CHAR_LENGTH(REPLACE(f_gedcom, \'\n2 PLAC\', \'\'))) / 7) > ((CHAR_LENGTH(f_gedcom) - CHAR_LENGTH(REPLACE(f_gedcom, \'\n3 _LOC\', \'\'))) / 7)');

    if (isset($params['start'], $params['end'])) {
        $query->whereBetween('f_id', [$params['start'], $params['end']]);
    }
    
    return $query->pluck('f_id');
  }
  
  public function doesRecordNeedUpdate(GedcomRecord $record, array $params): bool {
    if (!array_key_exists('mode', $params)) {
      return false;
    }
    
    if ($params['mode'] === 'hierarchicalize') {
      return $this->doesRecordNeedUpdateWrtHierarchy($record);
    }
        
    if ($params['mode'] === 'enhance') {
      return $this->doesRecordNeedUpdateWrtEnhance($record);
    }
    
    if ($params['mode'] === 'xrefs') {
      return $this->doesRecordNeedUpdateWrtXrefs($record);
    }
    
    if ($params['mode'] === 'create1') {
      return $this->doesRecordNeedUpdateWrtCreate($record, false);
    }
    
    if ($params['mode'] === 'create2') {
      return $this->doesRecordNeedUpdateWrtCreate($record, true);
    }
    
    return false;
  }
  
  protected function doesRecordNeedUpdateWrtHierarchy(GedcomRecord $record): bool {
    $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
    
    if (!$useHierarchy) {
      return false;
    }
    
    //we need immediate accepts in order to avoid potential duplicates when creating new shared places!
    if (Auth::user()->getPreference(User::PREF_AUTO_ACCEPT_EDITS) !== '1') {
      return false;
    }

    return preg_match($this->createRegex('1 NAME.*,.*\n'), $record->gedcom()) === 1;
  }
  
  protected function doesRecordNeedUpdateWrtEnhance(GedcomRecord $record): bool {
    if (!($record instanceof SharedPlace)) {
      return false;
    }
    
    if (($record->getLati() === null) && ($record->getLong() === null)) {
      foreach ($record->namesAsPlaces() as $place) {
        $ll = $this->getLatLon($place->gedcomName());
        if ($ll !== null) {
          return true;
        }
      }
    }
    
    $plac2GovSupporters = $this->getPlac2GovSupporters($record->tree());
    
    if ((sizeof($plac2GovSupporters) > 0) && $record->getGov() === null) {
      foreach ($plac2GovSupporters as $plac2GovSupporter) {
        foreach ($record->namesAsPlaces() as $place) {
          $gov = $plac2GovSupporter->plac2gov(PlaceStructure::fromPlace($place));
          if ($gov !== null) {
            return true;
          }
        }
      }
    }
    
    return false;
  }
      
  protected function doesRecordNeedUpdateWrtXrefs(GedcomRecord $record): bool {
    //is there actually a _LOC available for at least one PLAC with missing _LOC?
    
    foreach ($record->facts([]) as $fact) {
      $ps = PlaceStructure::fromFact($fact);
      if ($ps !== null) {
        $loc = $ps->getLoc();
        if ($loc !== null) {
          continue;
        }

        if ($this->placename2sharedPlace($ps->getGedcomName(), $ps->getTree()) !== null) {
          return true;
        }
      }
    }
    return false;
  }
  
  protected function doesRecordNeedUpdateWrtCreate(GedcomRecord $record, bool $unconditionally): bool {
    foreach ($record->facts([]) as $fact) {
      $ps = PlaceStructure::fromFact($fact);
      if ($ps !== null) {
        $loc = $ps->getLoc();
        if ($loc !== null) {
          continue;
        }
        
        if ($unconditionally) {
          return true;
        }
                
        if ($this->placename2sharedPlace($ps->getGedcomName(), $ps->getTree()) !== null) {
          //existing loc, but XREF is missing
          return true;
        }

        //is there global data available?
        $ll = $this->getLatLon($ps->getGedcomName());
        if ($ll !== null) {
          return true;
        }
        
        $plac2GovSupporters = $this->getPlac2GovSupporters($record->tree());
    
        if (sizeof($plac2GovSupporters) > 0) {
          foreach ($plac2GovSupporters as $plac2GovSupporter) {
            $gov = $plac2GovSupporter->plac2gov($ps);
            if ($gov !== null) {
              return true;
            }
          }
        }
      }
    }
    
    return false;
  }
  
  public function previewUpdate(GedcomRecord $record, array $params): string {
    if (!array_key_exists('mode', $params)) {
      return '';
    }
    
    if ($params['mode'] === 'hierarchicalize') {
      return $this->previewUpdateWrtHierarchy($record);
    }
    
    if ($params['mode'] === 'enhance') {
      return $this->previewUpdateWrtEnhance($record);
    }
        
    if ($params['mode'] === 'xrefs') {
      return $this->previewUpdateWrtXrefs($record);
    }
    
    if ($params['mode'] === 'create1') {
      return $this->previewUpdateWrtCreate($record, false);
    }
    
    if ($params['mode'] === 'create2') {
      return $this->previewUpdateWrtCreate($record, true);
    }
    
    return '';
  }
  
  protected function previewUpdateWrtHierarchy(GedcomRecord $record): string {
    $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
    
    if (!$useHierarchy) {
      return '';
    }

    //we need immediate accepts in order to avoid potential duplicates when creating new shared places!
    if (Auth::user()->getPreference(User::PREF_AUTO_ACCEPT_EDITS) !== '1') {
      return '';
    }
    
    if (!($record instanceof SharedPlace)) {
      return '';
    }
    
    $old = $record->gedcom();
    $new = $this->updateGedcomWrtHierarchy($record);
    
    //higher-level shared places for all names
    $creator = app(CreateSharedPlaceAction::class);
    $newlyCreated = '';
    
    foreach ($record->namesNN() as $placeGedcomName) {
      $parts = SharedPlace::placeNameParts($placeGedcomName);
      $tail = SharedPlace::placeNamePartsTail($parts);
      
      if ($tail !== '') {
        $parentRecord = $this->placename2sharedPlaceImpl($tail, $record->tree());
        
        if ($parentRecord != null) {
          //we may already have this link (added manually or in some other way)
          $link = "\n1 _LOC @" . $parentRecord->xref() . "@";
          if (!str_contains($new, $link)) {
            $new .= $link;
            $new .= "\n2 TYPE POLI";
          }
        } else {
          
          $ref = $creator->createIfRequired($tail, '', $record->tree(), true);
        
          if ($ref != null) {
            $new .= "\n1 _LOC @" . $ref->record()->xref() . "@";
            $new .= "\n2 TYPE POLI";
          }

          while (($ref !== null) && (!$ref->existed())) {
            $newlyCreated .= "\n";
            $newlyCreated .= str_replace("@@", "@" .$ref->record()->xref() . "@", $ref->record()->gedcom());
            $ref = $ref->parent();
          }
        }  
      }      
    }
    $new .= $newlyCreated;
    
    $data_fix_service = app(DataFixService::class);
    return $data_fix_service->gedcomDiff($record->tree(), $old, $new);
  }

  protected function previewUpdateWrtEnhance(GedcomRecord $record): string {
    $old = $record->gedcom();
    $new = $this->updateGedcomWrtEnhance($record);
    
    $data_fix_service = app(DataFixService::class);
    return $data_fix_service->gedcomDiff($record->tree(), $old, $new);
  }
    
  protected function previewUpdateWrtXrefs(GedcomRecord $record): string {
    $old = $record->gedcom();
    $new = $this->updateGedcomWrtXrefs($record);
    
    $data_fix_service = app(DataFixService::class);
    return $data_fix_service->gedcomDiff($record->tree(), $old, $new);
  }
  
  protected function previewUpdateWrtCreate(
          GedcomRecord $record, 
          bool $unconditionally): string {
    
    //we need immediate accepts in order to avoid potential duplicates when creating new shared places!
    if (Auth::user()->getPreference(User::PREF_AUTO_ACCEPT_EDITS) !== '1') {
      return '';
    }
    
    $old = $record->gedcom();
    
    //cf GedcomRecord::updateFact
    // First line of record may contain data - e.g. NOTE records.
    [$new_gedcom] = explode("\n", $record->gedcom(), 2);
    
    $creator = app(CreateSharedPlaceAction::class);
    $newlyCreated = '';
    
    //avoid previewing multiple creations for same place in multiple facts
    $refs = [];
    
    foreach ($record->facts([]) as $fact) {
      $factGedcom = $fact->gedcom();
      $newFactGedcom = null;
      
      $ps = PlaceStructure::fromFact($fact);
      if ($ps !== null) {
        $loc = $ps->getLoc();
        if ($loc === null) {
          
          $reused = false;
          if (array_key_exists($ps->getGedcomName(), $refs)) {
            $ref = $refs[$ps->getGedcomName()];
            $reused = true;
          } else {
            $ref = $creator->createIfRequired(
                  $ps->getGedcomName(), 
                  '', 
                  $record->tree(), 
                  true, 
                  $this,
                  !$unconditionally);  
          }
          
          if ($ref !== null) {
            $newFactGedcom = $this->updatePlacGedcomWithLoc($factGedcom, $ref->record()->xref());

            if (!$reused) {
              $refs[$ps->getGedcomName()] = $ref;
              
              while (($ref !== null) && (!$ref->existed())) {
                $newlyCreated .= "\n";
                $newlyCreated .= str_replace("@@", "@" .$ref->record()->xref() . "@", $ref->record()->gedcom());
                $ref = $ref->parent();
              }
            }
          }
        }
      }
      
      if ($newFactGedcom === null) {
        $newFactGedcom = $factGedcom;        
      }
      $new_gedcom .= "\n" . $newFactGedcom;
    }
    $new_gedcom .= $newlyCreated;
    
    $data_fix_service = app(DataFixService::class);
    return $data_fix_service->gedcomDiff($record->tree(), $old, $new_gedcom);
  }
  
  public function updateRecord(GedcomRecord $record, array $params): void {
    if (!array_key_exists('mode', $params)) {
      return;
    }
    
    if ($params['mode'] === 'hierarchicalize') {
      $this->updateRecordWrtHierarchy($record);
      return;
    }
        
    if ($params['mode'] === 'enhance') {
      $this->updateRecordWrtEnhance($record);
      return;
    }
    
    if ($params['mode'] === 'xrefs') {
      $this->updateRecordWrtXrefs($record);
      return;
    }
    
    if ($params['mode'] === 'create1') {
      $this->updateRecordWrtCreate($record, false);
      return;
    }

    if ($params['mode'] === 'create2') {
      $this->updateRecordWrtCreate($record, true);
      return;
    }
    
    return;
  }
  
  protected function updateRecordWrtHierarchy(GedcomRecord $record): void {
    $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
    
    if (!$useHierarchy) {
      return;
    }

    //we need immediate accepts in order to avoid potential duplicates when creating new shared places!
    if (Auth::user()->getPreference(User::PREF_AUTO_ACCEPT_EDITS) !== '1') {
      return;
    }
    
    $new = $this->updateGedcomWrtHierarchy($record);
    
    //higher-level shared places for all names
    $creator = app(CreateSharedPlaceAction::class);
    foreach ($record->namesNN() as $placeGedcomName) {
      $parts = SharedPlace::placeNameParts($placeGedcomName);
      $tail = SharedPlace::placeNamePartsTail($parts);
      
      if ($tail !== '') {
        $parentRecord = $this->placename2sharedPlaceImpl($tail, $record->tree());
        
        if ($parentRecord != null) {
          //we may already have this link (added manually or in some other way)
          $link = "\n1 _LOC @" . $parentRecord->xref() . "@";
          if (!str_contains($new, $link)) {
            $new .= $link;
            $new .= "\n2 TYPE POLI";
          }          
        } else {
          $ref = $creator->createIfRequired($tail, '', $record->tree());

          if ($ref != null) {
            $new .= "\n1 _LOC @" . $ref->record()->xref() . "@";
            $new .= "\n2 TYPE POLI";
          }
        }  
      }      
    }
    
    $record->updateRecord($new, false);
    
    //important to update the cache (this should be in webtrees, see https://github.com/fisharebest/webtrees/issues/3747)
    //otherwise this record may be found via search on updated names (via db), but with old parents (via cache)
    //(within same request, i.e. during updateAll)
    //leading to unintended duplicatesn
    //
    //test: create (without hierarchy)
    //A1, B, C, D, E
    //B, C, D, E
    //A2, B, C, D, E
    //switch to hierarchy
    //and run the data fix:
    //B is created twice (if unmake isn't called)
    Registry::locationFactory()->unmake($record->xref(), $record->tree());
    
    //we must check() in order to update place links
    Registry::locationFactory()->make($record->xref(), $record->tree()); 
  }
   
  protected function updateRecordWrtEnhance(GedcomRecord $record): void {
    $new = $this->updateGedcomWrtEnhance($record);
    
    $record->updateRecord($new, false);
    
    //strictly we should unmake here as well, in practice irrelevant
  }
    
  protected function updateRecordWrtXrefs(GedcomRecord $record): void {
    $new = $this->updateGedcomWrtXrefs($record);
    
    $record->updateRecord($new, false);
    
    //strictly we should unmake here as well, in practice irrelevant
  }
  
  protected function updateRecordWrtCreate(GedcomRecord $record, bool $unconditionally): void {
    //we need immediate accepts in order to avoid potential duplicates when creating new shared places!
    if (Auth::user()->getPreference(User::PREF_AUTO_ACCEPT_EDITS) !== '1') {
      return;
    }    
    
    $old = $record->gedcom();
    
    //cf GedcomRecord::updateFact
    // First line of record may contain data - e.g. NOTE records.
    [$new_gedcom] = explode("\n", $record->gedcom(), 2);
    
    $creator = app(CreateSharedPlaceAction::class);
    
    foreach ($record->facts([]) as $fact) {
      $factGedcom = $fact->gedcom();
      $newFactGedcom = null;
      
      $ps = PlaceStructure::fromFact($fact);
      if ($ps !== null) {
        $loc = $ps->getLoc();
        if ($loc === null) {
          
          $ref = $creator->createIfRequired(
                  $ps->getGedcomName(), 
                  '', 
                  $record->tree(), 
                  false, 
                  $this,
                  !$unconditionally);

          if ($ref !== null) {
            $newFactGedcom = $this->updatePlacGedcomWithLoc($factGedcom, $ref->record()->xref());
          }
        }
      }
      
      if ($newFactGedcom === null) {
        $newFactGedcom = $factGedcom;        
      }
      $new_gedcom .= "\n" . $newFactGedcom;
    }
    
    $record->updateRecord($new_gedcom, false);
  }
  
  protected function updatePlacGedcomWithLoc(string $factGedcom, string $xref): string {
    $factGedcomArray = explode("\n", $factGedcom);
    $insertAt = null;
    foreach ($factGedcomArray as $key => $factGedcomElement) {
      if (str_starts_with($factGedcomElement, "2 PLAC")) {
        $insertAt = $key;
      }
    }
    if ($insertAt !== null) {
      array_splice($factGedcomArray, $insertAt+1, 0, ["3 _LOC @" . $xref . "@"]);
    }            
    $newFactGedcom = implode("\n", $factGedcomArray);
    
    return $newFactGedcom;
  }
  
  protected function updateGedcomWrtHierarchy(GedcomRecord $record): string {
    $regex = $this->createRegex('(1 NAME[^,]*),[^\n]*');
    return preg_replace($regex, '$1$2', $record->gedcom());
  }
  
  protected function updateGedcomWrtEnhance(GedcomRecord $record): string {
    $gedcom = $record->gedcom();
    
    if (!($record instanceof SharedPlace)) {
      return $gedcom;
    }
    
    if (($record->getLati() === null) && ($record->getLong() === null)) {
      foreach ($record->namesAsPlaces() as $place) {
        $ll = $this->getLatLon($place->gedcomName());
        
        if ($ll !== null) {
          $map_lati = ($ll[0] < 0)?"S".str_replace('-', '', $ll[0]):"N".$ll[0];
          $map_long = ($ll[1] < 0)?"W".str_replace('-', '', $ll[1]):"E".$ll[1];
          $gedcom .= "\n1 MAP\n2 LATI ".$map_lati."\n2 LONG ".$map_long;
          break;
        }
      }
    }
    
    $plac2GovSupporters = $this->getPlac2GovSupporters($record->tree());
    
    if ((sizeof($plac2GovSupporters) > 0) && $record->getGov() === null) {      
      foreach ($plac2GovSupporters as $plac2GovSupporter) {
        foreach ($record->namesAsPlaces() as $place) {
          $gov = $plac2GovSupporter->plac2gov(PlaceStructure::fromPlace($place));
          if ($gov !== null) {
            $gedcom .= "\n1 _GOV ".$gov->getId();
            break;
          }
        }
      }
    }
    
    return $gedcom;
  }
    
  protected function updateGedcomWrtXrefs(GedcomRecord $record): string {
    //cf GedcomRecord::updateFact
    // First line of record may contain data - e.g. NOTE records.
    [$new_gedcom] = explode("\n", $record->gedcom(), 2);
    
    foreach ($record->facts([]) as $fact) {
      $factGedcom = $fact->gedcom();
      $newFactGedcom = null;
      
      $ps = PlaceStructure::fromFact($fact);
      if ($ps !== null) {
        $loc = $ps->getLoc();
        if ($loc === null) {
        $sharedPlace = $this->placename2sharedPlace($ps->getGedcomName(), $ps->getTree());
          if ($sharedPlace !== null) {
            $newFactGedcom = $this->updatePlacGedcomWithLoc($factGedcom, $sharedPlace->xref());
          }
        }
      }
      
      if ($newFactGedcom === null) {
        $newFactGedcom = $factGedcom;        
      }
      $new_gedcom .= "\n" . $newFactGedcom;
    }
    return $new_gedcom;
  }
  
  public function getPlac2GovSupporters(Tree $tree): array {
    $self = $this;
    return Registry::cache()->array()->remember('Plac2GovSupporters_'.$tree->id(), function () use ($self, $tree) {
        $functionsPlaceProviders = FunctionsPlaceUtils::accessibleModules($self, $tree, Auth::user())
            ->toArray();
        
        $plac2GovSupporters = [];
        
        foreach ($functionsPlaceProviders as $functionsPlaceProvider) {
          if ($functionsPlaceProvider->plac2govSupported()) {
            $plac2GovSupporters[] = $functionsPlaceProvider;
          }
        }
        
        return $plac2GovSupporters;
    });
  }
  
  //cf WebtreesLocationDataModule
  public function getLatLon(string $gedcomName): ?array {
    $location = new PlaceLocation($gedcomName);
    $latitude = $location->latitude();
    $longitude = $location->longitude();

    //wtf webtrees: 0.0; 0.0 are valid coordinates, why do you use them for 'unknown'?
    if (($latitude !== 0.0) && ($longitude !== 0.0)) {
      return array($latitude, $longitude);
    }

    return null;
  }
  
  protected function recordQuery(Builder $query, string $column, string $search): void {

    // Substituting newlines seems to be necessary on *some* versions
    //.of MySQL (e.g. 5.7), and harmless on others (e.g. 8.0).
    $search = strtr($search, ['\n' => "\n"]);

    switch (DB::connection()->getDriverName()) {
        case 'sqlite':
        case 'mysql':
            $query->where($column, 'REGEXP', $search);
            break;

        case 'pgsql':
            $query->where($column, '~', $search);
            break;

        case 'sqlsvr':
            // Not available
            break;
    }
  }
  
  protected function subst(string $search): string {
    // Substituting newlines seems to be necessary on *some* versions
    //.of MySQL (e.g. 5.7), and harmless on others (e.g. 8.0).
    $search = strtr($search, ['\n' => "\n"]);
    
    return $search;
  }
          
  protected function regexpOp(bool $invert = false): ?string {
    switch (DB::connection()->getDriverName()) {
        case 'sqlite':
        case 'mysql':
            return $invert?'NOT REGEXP':'REGEXP';
        case 'pgsql':
            return $invert?'!~':'~';
        case 'sqlsvr':
            return null;
    }
    
    return null;
  }
  
  protected function createRegex(string $search): string {

    $regex = '/' . addcslashes($search, '/') . '/';

    try {
        // A valid regex on an empty string returns zero.
        // An invalid regex on an empty string returns false and throws a warning.
        preg_match($regex, '');
    } catch (Throwable $ex) {
        $regex = self::INVALID_REGEX;
    }

    return $regex;
  }
}
