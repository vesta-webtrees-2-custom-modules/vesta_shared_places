<?php

namespace Cissee\Webtrees\Module\SharedPlaces;

use Aura\Router\Route;
use Aura\Router\RouterContainer;
use Cissee\Webtrees\Hook\HookInterfaces\EmptyIndividualFactsTabExtender;
use Cissee\Webtrees\Hook\HookInterfaces\IndividualFactsTabExtenderInterface;
use Cissee\WebtreesExt\AbstractModule;
use Cissee\WebtreesExt\Exceptions\SharedPlaceNotFoundException;
use Cissee\WebtreesExt\Factories\SharedPlaceFactory;
use Cissee\WebtreesExt\FactPlaceAdditions;
use Cissee\WebtreesExt\Http\Controllers\GenericPlaceHierarchyController;
use Cissee\WebtreesExt\Http\RequestHandlers\CreateSharedPlaceAction;
use Cissee\WebtreesExt\Http\RequestHandlers\CreateSharedPlaceModal;
use Cissee\WebtreesExt\Http\RequestHandlers\Select2Location;
use Cissee\WebtreesExt\Http\RequestHandlers\SharedPlacePage;
use Cissee\WebtreesExt\Module\ClippingsCartModule;
use Cissee\WebtreesExt\Services\SearchServiceExt;
use Cissee\WebtreesExt\SharedPlace;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Factory;
use Fisharebest\Webtrees\Functions\FunctionsPrint;
use Fisharebest\Webtrees\Functions\FunctionsPrintFacts;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\Middleware\AuthEditor;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleDataFixInterface;
use Fisharebest\Webtrees\Module\ModuleDataFixTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleListInterface;
use Fisharebest\Webtrees\Module\ModuleListTrait;
use Fisharebest\Webtrees\Services\DataFixService;
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
use Vesta\Hook\HookInterfaces\FunctionsClippingsCartInterface;
use Vesta\Hook\HookInterfaces\FunctionsPlaceInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsUtils;
use Vesta\Model\GenericViewElement;
use Vesta\Model\GovReference;
use Vesta\Model\LocReference;
use Vesta\Model\MapCoordinates;
use Vesta\Model\PlaceStructure;
use Vesta\Model\Trace;
use Vesta\VestaModuleTrait;
use function app;
use function redirect;
use function route;
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
  FunctionsClippingsCartInterface {

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
    $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
    if ($useHierarchy) {
      return $this->getListTitle(I18N::translate('Shared place hierarchy'));
    }
    
    //old-style list
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
    
    $locationsToFix = $this->locationsToFix($tree, []);
    $hasLocationsToFix = false;
    if ($locationsToFix) {
      $hasLocationsToFix = true;
    }
    
    $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
    if ($useHierarchy) {
      //TODO
      //$hasLocationsToFix;
      
      //$this->listUrl($tree)
      
      $searchService = app(SearchServiceExt::class);
      $controller = new GenericPlaceHierarchyController(
              new SharedPlaceHierarchyUtils($this, $searchService));

      return $controller->show($request);
    }

    //old-style list    
    $controller = new SharedPlacesListController($this, $hasLocationsToFix);

    $showLinkCounts = boolval($this->getPreference('LINK_COUNTS', '0'));
    return $controller->sharedPlacesList($tree, $showLinkCounts);
  }
  
  /**
   * Bootstrap the module
   */
  public function onBoot(): void {
      //define our 'pretty' routes
      //note: potentially problematic in case of name clashes; 
      //webtrees isn't interested in solving this properly, see
      //https://www.webtrees.net/index.php/en/forum/2-open-discussion/33687-pretty-urls-in-2-x
      
      $cache = app('cache.array');
      $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
      $useIndirectLinks = boolval($this->getPreference('INDIRECT_LINKS', '1'));
      $sharedPlaceFactory = new SharedPlaceFactory($cache, $useHierarchy, $useIndirectLinks);
      Factory::location($sharedPlaceFactory);

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
      // (adjustments for _LOC.NAME, _LOC.MAP, and _LOC._GOV)
      View::registerCustomView('::edit/add-fact', $this->name() . '::edit/add-fact');
      View::registerCustomView('::edit/edit-fact', $this->name() . '::edit/edit-fact');
      
      // Register a view under the main namespace (referred to from media-page)
      View::registerCustomView('::lists/shared-places-table', $this->name() . '::lists/shared-places-table');
      
      $createSharedPlaceModal = new CreateSharedPlaceModal($this);
      
      $router->get(CreateSharedPlaceModal::class, '/tree/{tree}/create-location', $createSharedPlaceModal)
              ->extras(['middleware' => [AuthEditor::class]]);
      
      $router->post(CreateSharedPlaceAction::class, '/tree/{tree}/create-location', CreateSharedPlaceAction::class)
              ->extras(['middleware' => [AuthEditor::class]]);
      
      //for SharedPlaceHierarchyController
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
      I18N::translate('Parent Shared Place');
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
      //(this is also TODO)
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
    //which is strictly not enforced (in particular the mdules aren't aware of the context of the edit control)
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
      $elementID = Uuid::uuid4();

      $expandSetting = $this->getPreference('EXPAND', '1');
      if ($expandSetting == '0') {
        $expand = false;
      } else if ($expandSetting == '1') {
        if (in_array($sharedPlace->xref(), SharedPlacesModule::$seenSharedPlaces)) {
          $expand = false;
        } else {
          $expand = true;
        }
        SharedPlacesModule::$seenSharedPlaces[] = $sharedPlace->xref();
      } else {
        $expand = true;
      }

      if ($expand) {
        $plusminus = 'icon-minus';
      } else {
        $plusminus = 'icon-plus';
      }
      $data .= '<a href="#" onclick="return expand_layer(\'' . $elementID . '\');"><i id="' . $elementID . '_img" class="' . $plusminus . '"></i></a> ';
      $data .= '<span class="label">' . I18N::translate('Shared place data') . '</span>';
      $data .= "<div id=\"$elementID\"";
      if ($expand) {
        $data .= ' style="display:block"';
      } else {
        $data .= ' style="display:none"';
      }
      $data .= ' class="shared_place_data">';
      $data .= $html;
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
          Tree $tree,
          bool $useHierarchy): ?SharedPlace {
    
    if ($placeGedcomName === '') {
      return null;
    }
    
    $searchService = app(SearchServiceExt::class);
    
    if ($useHierarchy) {
      $parts = explode(Gedcom::PLACE_SEPARATOR, $placeGedcomName);
      $head = reset($parts);
      $sharedPlaces = $searchService->searchLocations(array($tree), array("1 NAME " . $head . "\n"));
      foreach ($sharedPlaces as $sharedPlace) {
        if ($sharedPlace->matchesWithHierarchyAsArg($placeGedcomName, $useHierarchy)) {
          return $sharedPlace;
        }
      }
      return null;
    }
    
    $sharedPlaces = $searchService->searchLocations(array($tree), array("1 NAME " . $placeGedcomName . "\n"));
    foreach ($sharedPlaces as $sharedPlace) {
      if ($sharedPlace->matchesWithHierarchyAsArg($placeGedcomName, $useHierarchy)) {
        //first match wins, we don't expect multiple _LOC with same name
        //(for now) TODO resolve via date?
        return $sharedPlace;
      }
    }
    return null;
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
    
    $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
    $match = $this->placename2sharedPlaceImpl($placeGedcomName, $tree, $useHierarchy);
    
    if (($match === null) && ($parentLevels > 0)) {
      $placeGedcomName = implode(Gedcom::PLACE_SEPARATOR, array_slice(explode(Gedcom::PLACE_SEPARATOR, $placeGedcomName), 1));
      return $this->placename2sharedPlacePL($placeGedcomName, $tree, $parentLevels-1);
    }
    
    return $match;    
  }
  
  protected function plac2sharedPlace(PlaceStructure $ps): ?SharedPlace {
    $loc = $ps->getLoc();
    if ($loc !== null) {
      return Factory::gedcomRecord()->make($loc, $ps->getTree());
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
    $sharedPlace = Factory::gedcomRecord()->make($loc->getXref(), $loc->getTree());
    
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
    $sharedPlaces = $searchService->searchLocations(array($tree), array("1 _GOV " . $gov->getId() . "\n"));
    foreach ($sharedPlaces as $sharedPlace) {
      //first match wins
      $trace = $gov->getTrace();
      $trace->add('Location via Shared Places module');
      return new LocReference($sharedPlace->xref(), $tree, $trace, $gov->getLevel());
    }
    
    return null;
  }
  
  public function loc2map(LocReference $loc): ?MapCoordinates {
    $sharedPlace = Factory::gedcomRecord()->make($loc->getXref(), $loc->getTree());
    
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
    $sharedPlace = Factory::gedcomRecord()->make($loc->getXref(), $loc->getTree());
    
    if ($sharedPlace !== null) {
      if (!empty($sharedPlace->namesNN())) {
        $ps = PlaceStructure::fromNameAndLoc($sharedPlace->namesNN()[0], $sharedPlace->xref(), $sharedPlace->tree(), $loc->getLevel(), $sharedPlace);
        if ($ps !== null) {
          return $ps;
        }
      }  
    }
    
    return null;
  }
  
  public function loc2linkIcon(LocReference $loc): ?string {
    $sharedPlace = Factory::gedcomRecord()->make($loc->getXref(), $loc->getTree());
    
    if ($sharedPlace !== null) {
      return $this->getLinkForSharedPlace($sharedPlace);
    }
    
    return null;
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

        $sharedPlace = Factory::location()->make($xref, $tree);

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
 
        $sharedPlace = Factory::location()->make($xref, $tree);

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
    
  public function recordsToFix(Tree $tree, array $params): Collection {
    
    $locations = $this->locationsToFix($tree, $params);
    
    $records = new Collection();
    
    if ($locations !== null) {
        $records = $records->concat($this->mergePendingRecords($locations, $tree, Location::RECORD_TYPE));
    }
        
    return $records
        ->unique()
        ->sort(static function (stdClass $x, stdClass $y) {
            return $x->xref <=> $y->xref;
        });
  }

  public function doesRecordNeedUpdate(GedcomRecord $record, array $params): bool {
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

  public function previewUpdate(GedcomRecord $record, array $params): string {
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
    $new = $this->updateGedcom($record);
    
    //parent shared places for all names
    $creator = app(CreateSharedPlaceAction::class);
    $newlyCreated = '';
    foreach ($record->namesNN() as $placeGedcomName) {
      $parts = explode(Gedcom::PLACE_SEPARATOR, $placeGedcomName);
      $tail = implode(Gedcom::PLACE_SEPARATOR, array_slice($parts, 1));
      
      if ($tail !== '') {
        //shared place with hierarchical name is also ok here, we assume it will be handled later by data fix
        $parentRecord = $this->placename2sharedPlaceImpl($tail, $record->tree(), false);
        
        if ($parentRecord != null) {
          $new .= "\n1 _LOC @" . $parentRecord->xref() . "@";
          $new .= "\n2 TYPE POLI";
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

  public function updateRecord(GedcomRecord $record, array $params): void {
    $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
    
    if (!$useHierarchy) {
      return;
    }

    //we need immediate accepts in order to avoid potential duplicates when creating new shared places!
    if (Auth::user()->getPreference(User::PREF_AUTO_ACCEPT_EDITS) !== '1') {
      return;
    }
    
    $new = $this->updateGedcom($record);
    
    //parent shared places for all names
    $creator = app(CreateSharedPlaceAction::class);
    foreach ($record->namesNN() as $placeGedcomName) {
      $parts = explode(Gedcom::PLACE_SEPARATOR, $placeGedcomName);
      $tail = implode(Gedcom::PLACE_SEPARATOR, array_slice($parts, 1));
      
      if ($tail !== '') {
        //shared place with hierarchical name is also ok here, we assume it will be handled later by data fix
        $parentRecord = $this->placename2sharedPlaceImpl($tail, $record->tree(), false);
        
        if ($parentRecord != null) {
          $new .= "\n1 _LOC @" . $parentRecord->xref() . "@";
          $new .= "\n2 TYPE POLI";
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
  }
  
  /**
   * XREFs of location records that might need fixing.
   *
   * @param Tree                 $tree
   * @param array<string,string> $params
   *
   * @return Collection<string>|null
   */
  public function locationsToFix(Tree $tree, array $params): ?Collection {
    $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
    
    if (!$useHierarchy) {
      return null;
    }
    
    $query = DB::table('other')
        ->where('o_file', '=', $tree->id())
        ->where('o_type', '=', Location::RECORD_TYPE);

    $this->recordQuery($query, 'o_gedcom', '1 NAME.*,.*\n');

    return $query->pluck('o_id');
  }

  private function recordQuery(Builder $query, string $column, string $search): void
  {

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
  
  private function createRegex(string $search): string {

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
  
  private function updateGedcom(GedcomRecord $record): string {
    $regex = $this->createRegex('(1 NAME[^,]*),[^\n]*');
    return preg_replace($regex, '$1$2', $record->gedcom());
  }
}
