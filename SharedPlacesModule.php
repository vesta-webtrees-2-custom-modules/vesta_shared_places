<?php

namespace Cissee\Webtrees\Module\SharedPlaces;

use Fisharebest\Webtrees\Services\ModuleService;
use Cissee\Webtrees\Hook\HookInterfaces\EmptyIndividualFactsTabExtender;
use Cissee\Webtrees\Hook\HookInterfaces\IndividualFactsTabExtenderInterface;
use Vesta\Hook\HookInterfaces\EmptyFunctionsPlace;
use Vesta\Hook\HookInterfaces\FunctionsPlaceInterface;
use Cissee\WebtreesExt\AbstractModule; //cannot use original AbstractModule because we override setName
use Cissee\WebtreesExt\FormatPlaceAdditions;
use Cissee\WebtreesExt\Services\SearchServiceExt;
use Fisharebest\Localization\Locale\LocaleInterface;
use Cissee\WebtreesExt\GedcomRecordExt;
use Cissee\WebtreesExt\HtmlExt;
use Cissee\WebtreesExt\SharedPlaceFactory;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\Functions\FunctionsPrint;
use Fisharebest\Webtrees\Functions\FunctionsPrintFacts;
use Fisharebest\Webtrees\Http\Controllers\EditGedcomRecordController;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\ModuleInterface;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleListInterface;
use Fisharebest\Webtrees\Module\ModuleListTrait;
use Fisharebest\Webtrees\Tree;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Vesta\Model\PlaceStructure;
use Vesta\VestaModuleTrait;

class SharedPlacesModule extends AbstractModule implements ModuleCustomInterface, ModuleListInterface, ModuleConfigInterface, IndividualFactsTabExtenderInterface, FunctionsPlaceInterface {

  use VestaModuleTrait;
  use SharedPlacesModuleTrait;
  use EmptyIndividualFactsTabExtender;
  use EmptyFunctionsPlace;
  use ModuleListTrait;

  /** @var string The directory where the module is installed */
  protected $directory;

  public function __construct($directory) {
    $this->directory = $directory;
  }

  public function listTitle(): string {
    return $this->getListTitle(I18N::translate("Shared places"));
  }

  public function listMenuClass(): string {
    return 'menu-list-plac';
  }

  //public function setName(string $name): void {
  //	parent::setName($name);

  public function setEnabled(bool $enabled): ModuleInterface {
    parent::setEnabled($enabled);

    if ($enabled) {

      //cannot do the following in __construct: 
      //name not set yet!
      //enabled not set yet either!
      //extend GedcomRecord via GedcomRecordExt
      $useIndirectLinks = boolval($this->getPreference('INDIRECT_LINKS', '1'));
      GedcomRecordExt::addFactory('_LOC', new SharedPlaceFactory($this->name(), $useIndirectLinks));

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
    }

    return $this;
  }

  public function customModuleAuthorName(): string {
    return 'Richard Cissée';
  }

  public function customModuleVersion(): string {
    return '2.0.0-alpha.5.1';
  }

  public function customModuleLatestVersionUrl(): string {
    return 'https://cissee.de';
  }

  public function customModuleSupportUrl(): string {
    return 'https://cissee.de';
  }

  public function description(): string {
    return $this->getShortDescription();
  }

  /**
   * Where does this module store its resources
   *
   * @return string
   */
  public function resourcesFolder(): string {
    return __DIR__ . '/resources/';
  }

  /**
   * Additional/updated translations.
   *
   * @param string $language
   *
   * @return string[]
   */
  public function customTranslations(string $language): array {
    //TODO
    return [];
  }

  /**
   *
   * return SharedPlace|null	 
   */
  public function matchViaName(PlaceStructure $place) {
    $searchService = new SearchServiceExt(app(LocaleInterface::class));
    $sharedPlaces = $searchService->searchSharedPlaces(array($place->getTree()), array("1 NAME " . $place->getGedcomName()));
    foreach ($sharedPlaces as $sharedPlace) {
      foreach ($sharedPlace->namesNN() as $name) {
        if (strtolower($place->getGedcomName()) === strtolower($name)) {
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
      $sharedPlace = $this->matchViaName($place);
      if ($sharedPlace !== null) {
        return $sharedPlace;
      }
    }

    return $this->matchViaLoc($place);
  }

  //FunctionsPlaceInterface
  public function hPlacesGetLatLon(PlaceStructure $place) {
    $sharedPlace = $this->match($place);
    if ($sharedPlace !== null) {
      $lati = $sharedPlace->getLati();
      $long = $sharedPlace->getLong();

      if (($lati !== null) && ($long !== null)) {
        return array($lati, $long);
      }
    }

    return null;
  }

  public function hFactsTabGetFormatPlaceAdditions(PlaceStructure $place) {
    $ll = $this->hPlacesGetLatLon($place);
    $tooltip = null;
    if ($ll) {
      $tooltip = 'via shared place';
    }

    return new FormatPlaceAdditions('', $ll, $tooltip, '', $this->getHtmlForSharedPlaceData($place));
  }

  protected static $seenSharedPlaces = [];

  protected function getHtmlForSharedPlaceData(PlaceStructure $place) {
    //restrict to specific events?
    $restricted = $this->getPreference('RESTRICTED', '0');

    if ($restricted) {
      $restricted_indi = Filter::escapeHtml($this->getPreference('RESTRICTED_INDI', 'BIRT,MARR,OCCU,RESI,DEAT'));
      $restrictedTo = preg_split("/[, ;:]+/", $restricted_indi, -1, PREG_SPLIT_NO_EMPTY);
      if (!in_array($place->getEventType(), $restrictedTo, true)) {

        $restricted_fam = Filter::escapeHtml($this->getPreference('RESTRICTED_FAM', 'MARR'));
        $restrictedTo = preg_split("/[, ;:]+/", $restricted_fam, -1, PREG_SPLIT_NO_EMPTY);
        if (!in_array($place->getEventType(), $restrictedTo, true)) {
          return '';
        }
      }
    }

    $html = '';
    $sharedPlace = $this->match($place);
    if ($sharedPlace !== null) {
      //add all (level 1) notes
      if (preg_match('/1 NOTE (.*)/', $sharedPlace->gedcom(), $match)) {
        //note may be restricted - in which case, do not add wrapper
        //(and ultimately perhaps do not add entire 'shared place data', in case there is nothing else to display)
        $note = FunctionsPrint::printFactNotes($place->getTree(), $sharedPlace->gedcom(), 1);
        if ($note !== '') {
          $html .= '<div class="indent">';
          $html .= '<br>' . $note;
          $html .= '</div>';
        }
      }
      //add all (level 1) media
      if (preg_match_all("/1 OBJE @(.*)@/", $sharedPlace->gedcom(), $match)) {
        ob_start();
        FunctionsPrintFacts::printMediaLinks($place->getTree(), $sharedPlace->gedcom(), 1);
        $media = ob_get_clean();
        if ($media !== '') {
          $html .= $media;
          $html .= '<div class="indent">';
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
    return $html;
  }

  public function getListAction(Tree $tree): Response {
    $controller = new SharedPlacesListController($this->directory, $this->name());

    $showLinkCounts = boolval($this->getPreference('LINK_COUNTS', '0'));

    return $controller->sharedPlacesList($tree, $showLinkCounts);
  }

  public function getSingleAction(Request $request, Tree $tree): Response {
    $controller = new SharedPlaceController($this->directory, $this->name());
    return $controller->show($request, $tree);
  }

  public function getCreateSharedPlaceAction(): Response {
    $controller = new EditSharedPlaceController($this->directory, $this->name());
    return $controller->createSharedPlace();
  }

  public function postCreateSharedPlaceAction(Request $request, Tree $tree): Response {
    $controller = new EditSharedPlaceController($this->directory, $this->name());
    return $controller->createSharedPlaceAction($request, $tree);
  }

  //rerouted EditGedcomRecordController

  public function getEditRawRecordAction(Request $request, Tree $tree): Response {
    //no functional changes here - we just reroute through module
    $controller = new EditGedcomRecordController(app()->make(ModuleService::class));
    return $controller->editRawRecord($request, $tree);
  }

  public function postEditRawRecordAction(Request $request, Tree $tree): Response {
    //no functional changes here - we just reroute through module
    $controller = new EditGedcomRecordController(app()->make(ModuleService::class));
    return $controller->editRawRecordAction($request, $tree);
  }

  public function getEditRawFactAction(Request $request, Tree $tree): Response {
    //no functional changes here - we just reroute through module
    $controller = new EditGedcomRecordController(app()->make(ModuleService::class));
    return $controller->editRawFact($request, $tree);
  }

  public function postEditRawFactAction(Request $request, Tree $tree): Response {
    //no functional changes here - we just reroute through module
    $controller = new EditGedcomRecordController(app()->make(ModuleService::class));
    return $controller->editRawFactAction($request, $tree);
  }

  public function postCopyFactAction(Request $request, Tree $tree): Response {
    //no functional changes here - we just reroute through module
    $controller = new EditGedcomRecordController(app()->make(ModuleService::class));
    return $controller->copyFact($request, $tree);
  }

  public function postDeleteRecordAction(Request $request, Tree $tree): Response {
    //no functional changes here - we just reroute through module
    $controller = new EditGedcomRecordController(app()->make(ModuleService::class));
    return $controller->deleteRecord($request, $tree);
  }

  public function postPasteFactAction(Request $request, Tree $tree): Response {
    //no functional changes here - we just reroute through module
    $controller = new EditGedcomRecordController(app()->make(ModuleService::class));
    return $controller->pasteFact($request, $tree);
  }

  public function postDeleteFactAction(Request $request, Tree $tree): Response {
    //no functional changes here - we just reroute through module
    $controller = new EditGedcomRecordController(app()->make(ModuleService::class));
    return $controller->editFact($request, $tree);
  }

  public function getAddFactAction(Request $request, Tree $tree): Response {
    //no functional changes here - we just reroute through module
    $controller = new EditGedcomRecordController(app()->make(ModuleService::class));
    return $controller->addFact($request, $tree);
  }

  public function getEditFactAction(Request $request, Tree $tree): Response {
    //no functional changes here - we just reroute through module
    $controller = new EditGedcomRecordController(app()->make(ModuleService::class));
    return $controller->editFact($request, $tree);
  }

  public function postUpdateFactAction(Request $request, Tree $tree): Response {
    //no functional changes here - we just reroute through module
    $controller = new EditGedcomRecordController(app()->make(ModuleService::class));
    return $controller->updateFact($request, $tree);
  }

}
