<?php

namespace Cissee\Webtrees\Module\SharedPlaces;

use Aura\Router\RouterContainer;
use Cissee\Webtrees\Hook\HookInterfaces\EmptyIndividualFactsTabExtender;
use Cissee\Webtrees\Hook\HookInterfaces\IndividualFactsTabExtenderInterface;
use Cissee\WebtreesExt\AbstractModule;
use Cissee\WebtreesExt\FactPlaceAdditions;
use Cissee\WebtreesExt\GedcomRecordExt;
use Cissee\WebtreesExt\Http\RequestHandlers\SharedPlacePage;
use Cissee\WebtreesExt\Services\SearchServiceExt;
use Cissee\WebtreesExt\SharedPlace;
use Cissee\WebtreesExt\SharedPlaceFactory;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Functions\FunctionsPrint;
use Fisharebest\Webtrees\Functions\FunctionsPrintFacts;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleInterface;
use Fisharebest\Webtrees\Module\ModuleListInterface;
use Fisharebest\Webtrees\Module\ModuleListTrait;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Vesta\Hook\HookInterfaces\EmptyFunctionsPlace;
use Vesta\Hook\HookInterfaces\FunctionsPlaceInterface;
use Vesta\Model\GenericViewElement;
use Vesta\Model\GovReference;
use Vesta\Model\LocReference;
use Vesta\Model\MapCoordinates;
use Vesta\Model\PlaceStructure;
use Vesta\Model\Trace;
use Vesta\VestaModuleTrait;
use function app;
use function view;

//cannot use original AbstractModule because we override setName
class SharedPlacesModule extends AbstractModule implements 
  ModuleCustomInterface, 
  ModuleListInterface, 
  ModuleConfigInterface, 
  ModuleGlobalInterface, 
  IndividualFactsTabExtenderInterface, 
  FunctionsPlaceInterface {

  use ModuleCustomTrait, ModuleListTrait, ModuleConfigTrait, ModuleGlobalTrait, VestaModuleTrait {
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
  protected $hotfixRequired;

  public function __construct(
          ModuleService $module_service,
          bool $hotfixRequired) {
    
    $this->module_service = $module_service;
    $this->hotfixRequired = $hotfixRequired;
  }

  public function customModuleAuthorName(): string {
    return 'Richard Cissée';
  }

  public function customModuleSupportUrl(): string {
    return 'https://cissee.de';
  }

  public function description(): string {
    return $this->getShortDescription();
  }
  
  public function customModuleVersion(): string {
    return file_get_contents(__DIR__ . '/latest-version.txt');
  }

  public function customModuleLatestVersionUrl(): string {
    return 'https://raw.githubusercontent.com/vesta-webtrees-2-custom-modules/vesta_shared_places/master/latest-version.txt';
  }

  public function listTitle(): string {
    return $this->getListTitle(I18N::translate("Shared places"));
  }

  public function listMenuClass(): string {
    return 'menu-list-plac';
  }
  
  /**
   * Bootstrap the module
   */
  public function onBoot(): void {
      //define our 'pretty' routes
      //note: potentially problematic in case of name clashes; 
      //webtrees isn't interested in solving this properly, see
      //https://www.webtrees.net/index.php/en/forum/2-open-discussion/33687-pretty-urls-in-2-x
      
      $router_container = app(RouterContainer::class);
      assert($router_container instanceof RouterContainer);
      
      //(cf WebRoutes.php "Visitor routes with a tree")
      //note: this has the side effect of handling pricacy properly (Issue #9)
      $router_container->getMap()
        ->get(SharedPlacePage::class, '/tree/{tree}/sharedPlace/{xref}{/slug}', SharedPlacePage::class);    
    
      // Replace an existing view with our own version.
      // (media management via list module)
      View::registerCustomView('::modules/media-list/page', $this->name() . '::modules/media-list/page');
      
      // Register a view under the main namespace (referred to from modules/media-list/page)
      View::registerCustomView('::icons/shared-place', $this->name() . '::icons/shared-place');
      
      // Replace an existing view with our own version.
      // (media management via admin)
      View::registerCustomView('::media-page', $this->name() . '::media-page');
      
      // Replace an existing view with our own version.
      // (add fact with record, so that we can create proper links for new gov ids)
      View::registerCustomView('::edit/add-fact', $this->name() . '::edit/add-fact');
      
      // Register a view under the main namespace (referred to from media-page)
      View::registerCustomView('::lists/shared-places-table', $this->name() . '::lists/shared-places-table');
      
      if ($this->hotfixRequired) {
        $messages = Session::get('flash_messages', []);
        if (empty($messages)) {
          FlashMessages::addMessage(I18N::translate("Due to ongoing changes in the webtrees core code, the custom module 'Shared Places' is broken currently. You'll either have to disable it temporarily, or patch the webtrees core code by replacing a single file, as described in 'Hotfix.txt' in this module's folder. This will hopefully be fixed in the next webtrees release."), "warning");
        }              
      }
  }
      
  //do this onBoot instead?
  public function setEnabled(bool $enabled): ModuleInterface {
    parent::setEnabled($enabled);

    if ($enabled) {

      //cannot do the following in __construct: 
      //name not set yet!
      //enabled not set yet either!
      //extend GedcomRecord via GedcomRecordExt
      $useIndirectLinks = boolval($this->getPreference('INDIRECT_LINKS', '1'));
      GedcomRecordExt::addFactory('_LOC', new SharedPlaceFactory($useIndirectLinks));
    
    //this may never have been necessary: we're extending GedcomRecord in any case during module initialization,
    //never mind the routing!
    /*
      //extend Html via HtmlExt
      //(route through module in order to extend GedcomRecord via GedcomRecordExt,
      //in order to get proper routes for SharedPlace records pfff)
      //
      //cf web.php
      //but do this in particular only if the module is actually enabled (otherwise: urls won't resolve)!
      //GET and POST!
      HtmlExt::routeViaModule('edit-raw-record', $this->name(), 'EditRawRecord');

      //GET and POST!
      HtmlExt::routeViaModule('edit-raw-fact', $this->name(), 'EditRawFact');

      HtmlExt::routeViaModule('copy-fact', $this->name(), 'CopyFact');
      HtmlExt::routeViaModule('delete-fact', $this->name(), 'DeleteFact');
      HtmlExt::routeViaModule('paste-fact', $this->name(), 'PasteFact');

      HtmlExt::routeViaModule('delete-record', $this->name(), 'DeleteRecord');

      HtmlExt::routeViaModule('add-fact', $this->name(), 'AddFact');
      HtmlExt::routeViaModule('edit-fact', $this->name(), 'EditFact');
      HtmlExt::routeViaModule('update-fact', $this->name(), 'UpdateFact');
    */  
    }

    return $this;
  }

  /**
   * Where does this module store its resources
   *
   * @return string
   */
  public function resourcesFolder(): string {
    return __DIR__ . '/resources/';
  }
  
  //cf Place.php;
	const GEDCOM_SEPARATOR = ', ';

  public function matchViaName(string $placeName, Tree $tree, int $parentLevels): ?SharedPlace {
    if ($placeName === '') {
      return null;
    }
    $match = $this->matchName($tree, $placeName);
    
    if (($match === null) && ($parentLevels > 0)) {
      $placeName = implode(self::GEDCOM_SEPARATOR, array_slice(explode(self::GEDCOM_SEPARATOR, $placeName), 1));
      return $this->matchViaName($placeName, $tree, $parentLevels-1);
    }
    
    return $match;
  }
    
  public function matchName(Tree $tree, $placeGedcomName): ?SharedPlace {
    $locale = I18N::locale();
    
    $searchService = new SearchServiceExt($locale);
    $sharedPlaces = $searchService->searchSharedPlaces(array($tree), array("1 NAME " . $placeGedcomName));
    foreach ($sharedPlaces as $sharedPlace) {
      foreach ($sharedPlace->namesNN() as $name) {
        if (strtolower($placeGedcomName) === strtolower($name)) {
          //first match wins, we don't expect multiple _LOC with same name
          //(for now) TODO resolve via date?
          return $sharedPlace;
        }
      }
    }
    return null;
  }

  /**
   *
   * return SharedPlace|null	 
   */
  public function matchViaLoc(PlaceStructure $place) {
    $loc = $place->getLoc();
    if ($loc === null) {
      return null;
    }

    return GedcomRecordExt::getInstance($loc, $place->getTree());
  }

  /**
   *
   * return SharedPlace|null	 
   */
  public function match(PlaceStructure $place) {
    $indirect = boolval($this->getPreference('INDIRECT_LINKS', '1'));
    if ($indirect) {
      $parentLevels = intval($this->getPreference('INDIRECT_LINKS_PARENT_LEVELS', 0));
      $sharedPlace = $this->matchViaName($place->getGedcomName(), $place->getTree(), $parentLevels);
      if ($sharedPlace !== null) {
        return $sharedPlace;
      }
    }

    return $this->matchViaLoc($place);
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
    
    //align with current theme (supporting - for now - the default webtrees themes)
    $themeName = Session::get('theme');
    if ('minimal' !== $themeName) {
      if ('fab' === $themeName) {
        //fab also uses font awesome icons
        $themeName = 'minimal';
      } else {
        //default
        $themeName = 'webtrees';
      }      
    }
    
    $pre = '<link href="' . $this->assetUrl('css/'.$themeName.'.css') . '" type="text/css" rel="stylesheet" />';
		return $pre;
  } 
  
  //css for icons/shared-place
  public function hFactsTabGetOutputBeforeTab(Individual $person) {
		return new GenericViewElement('', '');
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
    $sharedPlace = $this->match($ps);
    //$sharedPlace = $this->matchName($fact->record()->tree(), $fact->place()->gedcomName());
    
    if ($sharedPlace !== null) {
      //already exists
      return new GenericViewElement('', '');
    }
    
    $html = view($this->name() . '::edit/icon-fact-create-shared-place', ['fact' => $fact, 'moduleName' => $this->name()]);
    
    return new GenericViewElement($html, '');
  }
  
  protected static $seenSharedPlaces = [];

  protected function getHtmlForSharedPlaceData(PlaceStructure $place) {
    //restrict to specific events?
    $restricted = $this->getPreference('RESTRICTED', '0');

    if ($restricted) {
      $restricted_indi = $this->getPreference('RESTRICTED_INDI', 'BIRT,MARR,OCCU,RESI,DEAT');
      $restrictedTo = preg_split("/[, ;:]+/", $restricted_indi, -1, PREG_SPLIT_NO_EMPTY);
      if (!in_array($place->getEventType(), $restrictedTo, true)) {

        $restricted_fam = $this->getPreference('RESTRICTED_FAM', 'MARR');
        $restrictedTo = preg_split("/[, ;:]+/", $restricted_fam, -1, PREG_SPLIT_NO_EMPTY);
        if (!in_array($place->getEventType(), $restrictedTo, true)) {
          return array('', '');
        }
      }
    }
    
    $html1 = '';
    $html = '';
    $sharedPlace = $this->match($place);
    if ($sharedPlace !== null) {
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
  
  public function getListAction(ServerRequestInterface $request): ResponseInterface {
    //'tree' is handled specifically in Router.php
    $tree = $request->getAttribute('tree');
    assert($tree instanceof Tree);
    
    $controller = new SharedPlacesListController($this);

    $showLinkCounts = boolval($this->getPreference('LINK_COUNTS', '0'));

    return $controller->sharedPlacesList($tree, $showLinkCounts);
  }

  public function getCreateSharedPlaceAction(ServerRequestInterface $request): ResponseInterface {
    //'tree' is handled specifically in Router.php
    $tree = $request->getAttribute('tree');
    assert($tree instanceof Tree);
    
    $controller = new EditSharedPlaceController($this);
    return $controller->createSharedPlace($request, $tree);
  }

  public function postCreateSharedPlaceAction(ServerRequestInterface $request): ResponseInterface {
    //'tree' is handled specifically in Router.php
    $tree = $request->getAttribute('tree');
    assert($tree instanceof Tree);
    
    $controller = new EditSharedPlaceController($this);
    return $controller->createSharedPlaceAction($request, $tree);
  }
  
  ////////////////////////////////////////////////////////////////////////////////
  
  public function plac2Loc(PlaceStructure $ps): ?LocReference {
    $loc = $ps->getLoc();
    if ($loc !== null) {
      $trace = new Trace('shared place via Shared Places module (gedcom _LOC tag)');
      return new LocReference($loc, $ps->getTree(), $trace);
    }
    
    $indirect = boolval($this->getPreference('INDIRECT_LINKS', '1'));
    if ($indirect) {
      $parentLevels = intval($this->getPreference('INDIRECT_LINKS_PARENT_LEVELS', 0));
      $sharedPlace = $this->matchViaName($ps->getGedcomName(), $ps->getTree(), $parentLevels);
      if ($sharedPlace !== null) {
        $trace = new Trace('shared place via Shared Places module (mapping via place name)');
        return new LocReference($sharedPlace->xref(), $sharedPlace->tree(), $trace);
      }
    }

    return null;
  }
  
  public function loc2Gov(LocReference $loc): ?GovReference {
    $sharedPlace = GedcomRecordExt::getInstance($loc->getXref(), $loc->getTree());
    
    if (($sharedPlace !== null) && ($sharedPlace instanceof SharedPlace)) {
      $gov = $sharedPlace->getGov();
      if ($gov !== null) {
        $trace = $loc->getTrace();
        $trace->add('GOV-Id via Shared Places module (gedcom _GOV tag)');
        return new GovReference($gov, $trace);
      }
    }
    
    return null;
  }
  
  public function loc2Map(LocReference $loc): ?MapCoordinates {
    $sharedPlace = GedcomRecordExt::getInstance($loc->getXref(), $loc->getTree());
    
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
  
  public function factPlaceAdditions(PlaceStructure $place): ?FactPlaceAdditions {
    //would be cleaner to use plac2loc here - in practice same result
    $htmls = $this->getHtmlForSharedPlaceData($place);
    return new FactPlaceAdditions(
            GenericViewElement::create($htmls[0]), 
            GenericViewElement::createEmpty(), 
            GenericViewElement::create($htmls[1]));
  }

}
