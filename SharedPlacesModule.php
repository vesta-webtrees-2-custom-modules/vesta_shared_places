<?php

namespace Cissee\Webtrees\Module\SharedPlaces;

use Cissee\Webtrees\Module\SharedPlaces\HelpTexts;
use Cissee\WebtreesExt\AbstractModule;
use Cissee\WebtreesExt\Elements\LanguageIdReplacement;
use Cissee\WebtreesExt\Elements\XrefSharedPlace;
use Cissee\WebtreesExt\Factories\SharedPlaceFactory;
use Cissee\WebtreesExt\Functions\FunctionsPrintExt;
use Cissee\WebtreesExt\Http\Controllers\ModulePlaceHierarchyInterface;
use Cissee\WebtreesExt\Http\Controllers\PlaceHierarchyLink;
use Cissee\WebtreesExt\Http\Controllers\PlaceHierarchyParticipant;
use Cissee\WebtreesExt\Http\Controllers\PlaceUrls;
use Cissee\WebtreesExt\Http\Controllers\PlaceWithinHierarchy;
use Cissee\WebtreesExt\Http\RequestHandlers\CreateSharedPlaceAction;
use Cissee\WebtreesExt\Http\RequestHandlers\CreateSharedPlaceModal;
use Cissee\WebtreesExt\Http\RequestHandlers\FunctionsPlaceProvidersAction;
use Cissee\WebtreesExt\Http\RequestHandlers\IndividualFactsTabExtenderProvidersAction;
use Cissee\WebtreesExt\Http\RequestHandlers\SharedPlacePage;
use Cissee\WebtreesExt\Http\RequestHandlers\TomSelectSharedPlace;
use Cissee\WebtreesExt\Module\ModuleMetaInterface;
use Cissee\WebtreesExt\Module\ModuleMetaTrait;
use Cissee\WebtreesExt\Module\ModuleVestalInterface;
use Cissee\WebtreesExt\Module\ModuleVestalTrait;
use Cissee\WebtreesExt\MoreI18N;
use Cissee\WebtreesExt\PlaceViaSharedPlace;
use Cissee\WebtreesExt\Requests;
use Cissee\WebtreesExt\Services\SearchServiceExt;
use Cissee\WebtreesExt\SharedPlace;
use Cissee\WebtreesExt\SharedPlacePreferences;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Elements\CustomElement;
use Fisharebest\Webtrees\Elements\GovIdType;
use Fisharebest\Webtrees\Elements\HierarchicalRelationship;
use Fisharebest\Webtrees\Elements\LocationRecord;
use Fisharebest\Webtrees\Elements\PlaceName;
use Fisharebest\Webtrees\Elements\UnknownElement;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\Middleware\AuthEditor;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateLocationAction;
use Fisharebest\Webtrees\Http\RequestHandlers\CreateLocationModal;
use Fisharebest\Webtrees\Http\RequestHandlers\LocationPage;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Location;
use Fisharebest\Webtrees\Module\LocationListModule;
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
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\User;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;
use Throwable;
use Vesta\CommonI18N;
use Vesta\Hook\HookInterfaces\ClippingsCartAddToCartInterface;
use Vesta\Hook\HookInterfaces\EmptyFunctionsPlace;
use Vesta\Hook\HookInterfaces\EmptyIndividualFactsTabExtender;
use Vesta\Hook\HookInterfaces\EmptyPrintFunctionsPlace;
use Vesta\Hook\HookInterfaces\FunctionsClippingsCartInterface;
use Vesta\Hook\HookInterfaces\FunctionsPlaceInterface;
use Vesta\Hook\HookInterfaces\FunctionsPlaceUtils;
use Vesta\Hook\HookInterfaces\GovIdEditControlsInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsUtils;
use Vesta\Hook\HookInterfaces\IndividualFactsTabExtenderInterface;
use Vesta\Hook\HookInterfaces\IndividualFactsTabExtenderUtils;
use Vesta\Hook\HookInterfaces\PrintFunctionsPlaceInterface;
use Vesta\Model\GedcomDateInterval;
use Vesta\Model\GenericViewElement;
use Vesta\Model\GovReference;
use Vesta\Model\LocReference;
use Vesta\Model\MapCoordinates;
use Vesta\Model\PlaceStructure;
use Vesta\Model\Trace;
use Vesta\VestaAdminController;
use Vesta\VestaModuleTrait;
use function app;
use function response;
use function route;
use function str_contains;
use function str_starts_with;
use function view;

//cannot use original AbstractModule because we override setName
class SharedPlacesModule extends AbstractModule implements 
    ModuleCustomInterface, 
    ModuleMetaInterface, 
    ModuleListInterface, 
    ModuleConfigInterface, 
    ModuleGlobalInterface, 
    ModuleDataFixInterface, 
    ModuleVestalInterface,
    IndividualFactsTabExtenderInterface, 
    FunctionsPlaceInterface,
    PrintFunctionsPlaceInterface,
    FunctionsClippingsCartInterface,
    PlaceHierarchyParticipant, 
    RequestHandlerInterface {

    use ModuleCustomTrait, ModuleMetaTrait, ModuleListTrait, ModuleConfigTrait, ModuleGlobalTrait, VestaModuleTrait, ModuleDataFixTrait, ModuleVestalTrait {
        VestaModuleTrait::customTranslations insteadof ModuleCustomTrait;
        VestaModuleTrait::getAssetAction insteadof ModuleCustomTrait;
        VestaModuleTrait::assetUrl insteadof ModuleCustomTrait;    
        VestaModuleTrait::getConfigLink insteadof ModuleConfigTrait;
        ModuleMetaTrait::customModuleVersion insteadof ModuleCustomTrait;
        ModuleMetaTrait::customModuleLatestVersion insteadof ModuleCustomTrait;
    }

    use SharedPlacesModuleTrait;
    use EmptyIndividualFactsTabExtender;
    use EmptyFunctionsPlace;
    use EmptyPrintFunctionsPlace;

    protected $module_service;  
  
    //list
    protected const ROUTE_URL = '/tree/{tree}/shared-place-list';
  
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
 
    public function customModuleMetaDatasJson(): string {
        return file_get_contents(__DIR__ . '/metadata.json');
    } 

    public function customModuleLatestMetaDatasJsonUrl(): string {
        return 'https://raw.githubusercontent.com/vesta-webtrees-2-custom-modules/vesta_shared_places/master/metadata.json';
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

    public function listUrl(Tree $tree, array $parameters = []): string {
        
        $parameters['tree'] = $tree->name();
        
        return route(SharedPlacesModule::class, $parameters);
    }
    
    public function handle(ServerRequestInterface $request): ResponseInterface {
        
        $tree = Validator::attributes($request)->tree();
        $user = Validator::attributes($request)->user();
        
        Auth::checkComponentAccess($this, ModuleListInterface::class, $tree, $user);

        $link = null;
        $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
        $locationsToFix = $this->locationsToFixWrtHierarchy($tree);
        $hasLocationsToFix = ($locationsToFix->count() > 0);

        if ($useHierarchy && !$hasLocationsToFix) {
            //link to place hierarchy
            
            //Issue #28
            //we're looking for the first ModuleListInterface that is also a ModulePlaceHierarchyInterface
            //(and which the user is allowed to see)
            //
            //note: we cannot use 
            //->findByComponent(ModulePlaceHierarchyInterface::class, $tree, Auth::user())
            //directly because the access level isn't set for this specific interface!
            $module = app(ModuleService::class)
                ->findByComponent(ModuleListInterface::class, $tree, Auth::user())
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
                //only show this to admins!
                if (Auth::isAdmin()) {
                    //technically, the module may be enabled but its list component may be hidden from everybody,
                    //so this message is sometimes misleading
                    $link = new PlaceHierarchyLink(I18N::translate("Enable the Vesta Places and Pedigree map module to view the shared places hierarchy."), null, null);
                }
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

        //explicitly register in order to re-use in views where we cannot pass via variable
        //(could also resolve via module service)
        app()->instance(SharedPlacesModule::class, $this); //do not use bind()! for some reason leads to 'Illegal offset type in isset or empty'

        $useHierarchy = boolval($this->getPreference('USE_HIERARCHY', '1'));
        $useIndirectLinks = boolval($this->getPreference('INDIRECT_LINKS', '0'));
        
        //TODO REVISIT ALL OF THIS
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
      
        $sharedPlaceFactory = new SharedPlaceFactory($preferences);
        Registry::locationFactory($sharedPlaceFactory);
      
        //define our 'pretty' routes
        //note: potentially problematic in case of name clashes; 
        //webtrees isn't interested in solving this properly, see
        //https://www.webtrees.net/index.php/en/forum/2-open-discussion/33687-pretty-urls-in-2-x

        /*
        $router_container = app(RouterContainer::class);
        assert($router_container instanceof RouterContainer);
        $router = $router_container->getMap();
        */
      
        $router = Registry::routeFactory()->routeMap();
            
        //(cf WebRoutes.php "Visitor routes with a tree")
        //note: this format has the side effect of handling privacy properly (Issue #9)
        //from 2.0.12, use standard name with specific handler!

        //for this, we have to remove the original route, otherwise: RouteAlreadyExists (meh)
        $existingRoutes = $router->getRoutes();
        if (array_key_exists(LocationPage::class, $existingRoutes)) {
            unset($existingRoutes[LocationPage::class]);        
        }
      
        //while we're at it: webtrees 2.0.12 has CreateLocationModal and CreateLocationAction on the same urls
        //as our CreateSharedPlaceModal and CreateSharedPlaceAction, we have to drop those!
        if (array_key_exists(CreateLocationModal::class, $existingRoutes)) {
            unset($existingRoutes[CreateLocationModal::class]);        
        }
        
        if (array_key_exists(CreateLocationAction::class, $existingRoutes)) {
            unset($existingRoutes[CreateLocationAction::class]);        
        }
      
        //no - we register a TomSelectLocation route ourselves 
        //(use to have name TomSelectLocation as well, that's changed now),
        //but we still need the original select control e.g. for merge records
        /*
        if (array_key_exists("Fisharebest\Webtrees\Http\RequestHandlers\TomSelectLocation", $existingRoutes)) {
            unset($existingRoutes["Fisharebest\Webtrees\Http\RequestHandlers\TomSelectLocation"]);        
        }
        */
      
        //no longer required (webtrees #3786)
        //if (array_key_exists(SearchGeneralPage::class, $existingRoutes)) {
        //  unset($existingRoutes[SearchGeneralPage::class]);        
        //}
        //
        //if (array_key_exists(SearchGeneralAction::class, $existingRoutes)) {
        //  unset($existingRoutes[SearchGeneralAction::class]);        
        //}
      
        if (array_key_exists(LocationListModule::class, $existingRoutes)) {
            unset($existingRoutes[LocationListModule::class]);        
        }
        
        $router->setRoutes($existingRoutes);

        $router->get(LocationPage::class, '/tree/{tree}/sharedPlace/{xref}{/slug}', SharedPlacePage::class);    

        $router->get(static::class, static::ROUTE_URL, $this);
        
        //also redirect webtrees location-list, otherwise confusing to have to apparently similar lists
        //(actual difference: create shared place button and other additions on top of actual list)
        //(this makes our list menu entry somewhat redundant though)
        //unset: see above
        $router->get(LocationListModule::class, static::ROUTE_URL, $this);
        
        //no longer required (webtrees #3786)
        //$router->get(SearchGeneralPage::class, '/tree/{tree}/search-general', SearchGeneralPageExt::class);    
        //$router->post(SearchGeneralAction::class, '/tree/{tree}/search-general', SearchGeneralActionExt::class);    
        //View::registerCustomView('::search-general-page-ext', $this->name() . '::search-general-page-ext');
        //View::registerCustomView('::search-results-ext', $this->name() . '::search-results-ext');

        // no longer required (TODO re-check this)
        //
        // Register a view under the main namespace (referred to from modules/media-list/page)
        //View::registerCustomView('::icons/shared-place', $this->name() . '::icons/shared-place');
        //
        // Replace an existing view with our own version.
        // (record icons e.g. for clippings cart)
        //View::registerCustomView('::icons/record', $this->name() . '::icons/record');

        // no longer required (webtrees #4250)
        //      
        // Replace an existing view with our own version.
        // (media management via list module)
        //View::registerCustomView('::modules/media-list/page', $this->name() . '::modules/media-list/page');
        // Replace an existing view with our own version.
        // (media management via admin, and via list module)
        // Replace an existing view with our own version.
        //View::registerCustomView('::note-page', $this->name() . '::note-page');      
        // Replace an existing view with our own version.
        //View::registerCustomView('::source-page', $this->name() . '::source-page');

        // Replace an existing view with our own version.
        // (referred to from media-page, from search results, etc)
        View::registerCustomView('::lists/locations-table', $this->name() . '::lists/locations-table');
        
        //plus adjustment for coordinates
        View::registerCustomView('::edit/fact-location-edit', $this->name() . '::edit/fact-location-edit');
        
        //adjustment for ajax-modal-vesta
        View::registerCustomView('::edit/edit-fact', $this->name() . '::edit/edit-fact');
        
        $createSharedPlaceModal = new CreateSharedPlaceModal($this);
      
        $router->get(CreateSharedPlaceModal::class, '/tree/{tree}/create-location', $createSharedPlaceModal)
            ->extras(['middleware' => [AuthEditor::class]]);
      
        $router->post(CreateSharedPlaceAction::class, '/tree/{tree}/create-location', CreateSharedPlaceAction::class)
            ->extras(['middleware' => [AuthEditor::class]]);
      
        //TODO: cleanup - remove and/or integrate!
        //(main difference is note in page.phtml wrt hasLocationsToFix, everything else was cosmetic and apparently obsolete!)
        //(was maybe intended for separate shared places hierarchy list?)
        //for GenericPlaceHierarchyController
        //View::registerCustomView('::modules/generic-place-hierarchy-shared-places/place-hierarchy', $this->name() . '::modules/generic-place-hierarchy-shared-places/place-hierarchy');
        //View::registerCustomView('::modules/generic-place-hierarchy-shared-places/list', $this->name() . '::modules/generic-place-hierarchy-shared-places/list');
        //View::registerCustomView('::modules/generic-place-hierarchy-shared-places/page', $this->name() . '::modules/generic-place-hierarchy-shared-places/page');
        //View::registerCustomView('::modules/generic-place-hierarchy-shared-places/sidebar', $this->name() . '::modules/generic-place-hierarchy-shared-places/sidebar');
      
        ////////////////////////////////////////////////////////////////////////////
        // Location support, some of this overlaps with webtrees core now
      
        //note that webtrees now (starting 2.0.16) also defines this view!
        //still easier to use our view, but not everywhere (i.e. not when merging records)
        //used via XrefSharedPlace
        View::registerCustomView('::components/select-location-ext', $this->name() . '::components/select-location');
      
        View::registerCustomView('::selects/location', $this->name() . '::selects/location');
      
        $router->get(TomSelectSharedPlace::class, '/tree/{tree}/tom-select-shared-place', TomSelectSharedPlace::class);
      
        ////////////////////////////////////////////////////////////////////////////
      
        /* I18N: translate just like 'Shared Place' for consistency */I18N::translate('Location');
        /* I18N: translate just like 'Shared Places' for consistency */I18N::translate('Locations');

        $ef = Registry::elementFactory();

        /*
        //make sure Gedcom-L tags are available even if the preference isn't checked
        //
        //this is no longer required - they are always available now!
        //see Gedcom.php
        //(but not always for editing, see customSubTags() in Gedcom.php)
        */
      
        //but we need more (this partially overlaps with customSubTags() in Gedcom.php)
        $ef->registerSubTags($this->customSubTags());
        /*
        foreach ($this->customSubTags() as $tag => $children) {
            $element = $ef->make($tag);
            foreach ($children as $child) {
                $element->subtag(...$child);
            }
        }
        */
      
        //for now, keep established terminology for specific tags      
        $ef->registerTags([
            //redundant, we swap translation globally anyway!
            'INDI:*:PLAC:_LOC' => new XrefSharedPlace(I18N::translate('Shared place')),
            //redundant, we swap translation globally anyway!
            'FAM:*:PLAC:_LOC' => new XrefSharedPlace(I18N::translate('Shared place')),

            //redundant, we swap translation globally anyway!
            'SOUR:DATA:EVEN:PLAC:_LOC' => new XrefSharedPlace(I18N::translate('Shared place')),
            
            //redundant, we swap translation globally anyway!
            '_LOC' => new LocationRecord(I18N::translate('Shared place')),

            //'Place' seems confusing here - if hierarchical shared places are used, this should be just one part of the place name
            '_LOC:NAME' => new PlaceName(MoreI18N::xlate('Name'), ['ABBR' => '0:1', 'DATE' => '0:1', 'LANG' => '0:1', 'SOUR' => '0:M']),

            '_LOC:_LOC' => new XrefSharedPlace(I18N::translate('Higher-level shared place'), ['DATE' => '0:1', 'SOUR' => '0:M', 'TYPE' => '0:1']),
            //'_LOC:TYPE' => new CustomElement(I18N::translate('Type of location')), //anyway requires subtags!
            '_LOC:_LOC:TYPE' => new HierarchicalRelationship(I18N::translate('Type of hierarchical relationship')),
            
            '_LOC:NAME:LANG' => new LanguageIdReplacement(MoreI18N::xlate('Language')),
        ]);
        
        //register fallback in case Gov4Webtrees isn't active:
        //(webtrees registers this as CustomElement via gedcomLTags(), 
        //even though there is something more specific in webtrees core code)
        $element = $ef->make('_LOC:TYPE:_GOVTYPE');
        if (($element instanceof CustomElement) || ($element instanceof UnknownElement)) {
            $ef->registerTags([
                '_LOC:TYPE:_GOVTYPE' => new GovIdType(I18N::translate('GOV id for type of location')),
            ]);    
        }
      
        $this->flashWhatsNew('\Cissee\Webtrees\Module\SharedPlaces\WhatsNew', 4);
    }
  
    //this should probably be in Gedcom::gedcomLTags
    /**
     * @return array<string,array<int,array<int,string>>>
     */
    protected function customSubTags(): array {
        return [
            'FAM:*:PLAC' => [['_LOC', '0:1']],
            'INDI:*:PLAC' => [['_LOC', '0:1']],
            'SOUR:DATA:EVEN:PLAC' => [['_LOC', '0:1']],
            
            //the following are defined explicitly in ElementFactory, so we must address explicitly!
            //(not enough to handle via wildcard)
            'FAM:ENGA:PLAC' => [['_LOC', '0:1']],
            'FAM:MARB:PLAC' => [['_LOC', '0:1']],
            'FAM:MARR:PLAC' => [['_LOC', '0:1']],
            'FAM:SLGS:PLAC' => [['_LOC', '0:1']],            
            
            'INDI:ADOP:PLAC' => [['_LOC', '0:1']],
            'INDI:BAPL:PLAC' => [['_LOC', '0:1']],
            'INDI:BAPM:PLAC' => [['_LOC', '0:1']],
            'INDI:BARM:PLAC' => [['_LOC', '0:1']],
            'INDI:BASM:PLAC' => [['_LOC', '0:1']],
            'INDI:BIRT:PLAC' => [['_LOC', '0:1']],
            'INDI:BLES:PLAC' => [['_LOC', '0:1']],
            'INDI:BURI:PLAC' => [['_LOC', '0:1']],
            'INDI:CENS:PLAC' => [['_LOC', '0:1']],
            'INDI:CHR:PLAC' => [['_LOC', '0:1']],
            'INDI:CONF:PLAC' => [['_LOC', '0:1']],
            'INDI:CONL:PLAC' => [['_LOC', '0:1']],
            'INDI:CREM:PLAC' => [['_LOC', '0:1']],
            'INDI:DEAT:PLAC' => [['_LOC', '0:1']],
            'INDI:EMIG:PLAC' => [['_LOC', '0:1']],
            'INDI:ENDL:PLAC' => [['_LOC', '0:1']],
            'INDI:EVEN:PLAC' => [['_LOC', '0:1']],
            'INDI:FCOM:PLAC' => [['_LOC', '0:1']],
            'INDI:IMMI:PLAC' => [['_LOC', '0:1']],
            'INDI:NATU:PLAC' => [['_LOC', '0:1']],
            'INDI:ORDN:PLAC' => [['_LOC', '0:1']],
            'INDI:RESI:PLAC' => [['_LOC', '0:1']],
            'INDI:SLGC:PLAC' => [['_LOC', '0:1']],
        ];
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
  
    public function bodyContent(): string {
        return view($this->name() . '::js/webtreesExt');
    }
    
    public function hFactsTabGetStyleadds(
        GedcomRecord $record,
        Fact $fact): array {
        
        //is it one of our facts?
        foreach ($this->hFactsTabGetAdditionalFacts($record) as $additionalFact) {
            if ($additionalFact->id() === $fact->id()) {
                $styleadds = [];
                $styleadds[] = 'wt-location-fact-pfh collapse'; //see hFactsTabGetOutputInDBox
                return $styleadds;
            }
        }
        
        return [];        
    }
    
    public function hFactsTabGetAdditionalFacts(
        GedcomRecord $record) {
        
        if (!($record instanceof SharedPlace)) {
            return [];
        }
        
        //use these regardless of any PLAC set there
        //(which is anyway dubious and perhaps should be removed from the spec?)
	return $record->facts(['FACT','EVEN']);
    }
    
    public function hFactsTabGetOutputInDBox(
        GedcomRecord $record): GenericViewElement {
        
        if (sizeof($this->hFactsTabGetAdditionalFacts($record)) === 0) {
            return GenericViewElement::createEmpty();
        }
        
	$toggleable = true/*boolval($this->getPreference('TAB_TOGGLEABLE_LOC_FACTS', '1'))*/;
	return $this->getOutputInDescriptionBox($toggleable, 'show-location-facts-factstab', 'wt-location-fact-pfh', I18N::translate('Shared place data'));
    }
  
    protected function getOutputInDescriptionBox(
        bool $toggleable, 
        string $id, 
        string $targetClass,           
        string $label) {
      
        ob_start();
        if ($toggleable) {
          ?>
          <label>
              <input id="<?php echo $id; ?>" type="checkbox" data-bs-toggle="collapse" data-bs-target=".<?php echo $targetClass; ?>" data-wt-persist="<?php echo $targetClass; ?>" autocomplete="off">
              <?php echo $label; ?>
          </label>
          <?php
        }

        return new GenericViewElement(ob_get_clean(), '');
    }
  
    public function hFactsTabGetOutputAfterTab(
        GedcomRecord $record,
        bool $ajax): GenericViewElement {
                
        if (!$ajax) {
            //nothing to do - in fact must not initialize twice!
            return GenericViewElement::createEmpty();
        }
        
        if (sizeof($this->hFactsTabGetAdditionalFacts($record)) === 0) {
            return GenericViewElement::createEmpty();
        }
        
        $toggleable = true/*boolval($this->getPreference('TAB_TOGGLEABLE_LOC_FACTS', '1'))*/;
        return $this->getOutputAfterTab($toggleable, 'show-location-facts-factstab');
    }
  
    protected function getOutputAfterTab($toggleable, $toggle) {
        $post = "";

        if ($toggleable) {
          $post = $this->getScript($toggle);
        }

        return new GenericViewElement('', $post);
    }

    protected function getScript($toggle) {
        ob_start();
        ?>
        <script>
          webtrees.persistentToggle(document.querySelector('#<?php echo $toggle; ?>'));
        </script>
        <?php
        return ob_get_clean();
    }
  
    public function hFactsTabRequiresModalVesta(Tree $tree): ?string {
        //required via CreateSharedPlaceAction
        $additionalControls = GovIdEditControlsUtils::accessibleModules($tree, Auth::user())
            ->map(function (GovIdEditControlsInterface $module) {
              return $module->govIdEditControlSelectScriptSnippet();
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
    
        $useIndirectLinks = boolval($this->getPreference('INDIRECT_LINKS', '0'));
    
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
        } else {
            //Issue #123
            //unexpected, cf earlier check ($fact->attribute('PLAC') === '')
            //anyway moving on
            return new GenericViewElement('', '');
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

    public function linkIcon($view, $title, $url) {
        return '<a href="' . $url . '" rel="nofollow" title="' . $title . '">' .
            view($view) .
            '<span class="visually-hidden">' . $title . '</span>' .
            '</a>';
    }
  
    ////////////////////////////////////////////////////////////////////////////
  
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

        $indirect = boolval($this->getPreference('INDIRECT_LINKS', '0'));
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
    
        $indirect = boolval($this->getPreference('INDIRECT_LINKS', '0'));
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
  
    public function locPloc(
        LocReference $locReference, 
        GedcomDateInterval $dateInterval, 
        Collection $typesOfLocation, 
        int $maxLevels = PHP_INT_MAX): Collection {
    
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
  
    public function factPlaceAdditionsBeforePlace(PlaceStructure $place): ?string {
        $sharedPlace = $this->plac2sharedPlace($place);
        if ($sharedPlace === null) {
            return null;
        }

        return $this->getLinkForSharedPlace($sharedPlace);
    }
  
    public function factPlaceAdditionsAfterMap(PlaceStructure $place): ?string {
        return null;
    }
  
    public function factPlaceAdditionsAfterNotes(PlaceStructure $place): ?string {
        //would be cleaner to use plac2loc here - in practice same result
        $sharedPlace = $this->plac2sharedPlace($place);
        if ($sharedPlace === null) {
            return null;
        }

        $html = '';
            
        //restrict to specific events?
        $restricted = boolval($this->getPreference('RESTRICTED', '0'));

        if ($restricted) {
            $restricted_indi = $this->getPreference('RESTRICTED_INDI', 'BIRT,MARR,OCCU,RESI,DEAT');
            $restrictedTo = preg_split("/[, ;:]+/", $restricted_indi, -1, PREG_SPLIT_NO_EMPTY);
            if (!in_array($place->getEventType(), $restrictedTo, true)) {

                $restricted_fam = $this->getPreference('RESTRICTED_FAM', 'MARR');
                $restrictedTo = preg_split("/[, ;:]+/", $restricted_fam, -1, PREG_SPLIT_NO_EMPTY);
                if (!in_array($place->getEventType(), $restrictedTo, true)) {
                    return null;
                }
            }
        }
    
        //add name if different from PLAC name
        $nameAt = $sharedPlace->primaryPlaceAt($place->getEventDateInterval())->gedcomName();
        if ($nameAt !== $place->getGedcomName()) {
            $html .= '<div class="ps-4 indent">';
            $html .= $nameAt;
            $html .= '</div>';
        }
    
        //add all (level 1) notes
        if (preg_match('/1 NOTE (.*)/', $sharedPlace->gedcom(), $match)) {
            //note may be restricted - in which case, do not add wrapper
            //(and ultimately perhaps do not add entire 'shared place data', in case there is nothing else to display)
            $note = view($this->name() . '::fact-notes-shared-place', ['fact' => $sharedPlace]);
            if ($note !== '') {
                $html .= '<div class="ps-4 indent">';
                $html .= $note;
                //$html .= '<br>';
                $html .= '</div>';
            }
        }
        
        //add all (level 1) media
        if (preg_match_all("/1 OBJE @(.*)@/", $sharedPlace->gedcom(), $match)) {
            $media = view($this->name() . '::fact-media-shared-place', ['fact' => $sharedPlace]);
            if ($media !== '') {
                $html .= '<div class="ps-4 indent">';
                $html .= $media;
                $html .= '<br class="media-separator" style="clear:both;">'; //otherwise layout issues wrt following elements, TODO handle differently!
                $html .= '</div>';
            }
        }

        //add all (level 1) sources
        if (preg_match_all("/1 SOUR @(.*)@/", $sharedPlace->gedcom(), $match)) {
            $sources = view($this->name() . '::fact-sources-shared-place', ['fact' => $sharedPlace]);
            if ($sources !== '') {
                $html .= '<div class="ps-4 indent">';
                $html .= $sources;
                $html .= '<br class="media-separator" style="clear:both;">'; //otherwise layout issues wrt following elements, TODO handle differently!
                $html .= '</div>';
            }
        }
      
        if ($html == '') {
            return null;
        }
    
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

        $data .= '<a href="#' . e($id) . '" role="button" data-bs-toggle="collapse" aria-controls="' . e($id) . '" aria-expanded="' . ($expanded ? 'true' : 'false') . '">' .
            view('icons/expand') .
            view('icons/collapse') .
            '</a> ';

        $data .= '<span class="label"><a href="' . $sharedPlace->url() . '">' . I18N::translate('Shared place data') . '</a></span>';
        $data .= '<div id="' . e($id) . '" class="shared_place_data collapse ' . ($expanded ? 'show' : '') . '">' .
            $html .
            '</div>';
        $data .= '</div>';
    
        return $data;
    }
  
    ////////////////////////////////////////////////////////////////////////////
    //FunctionsClippingsCartInterface
  
    public function getAddLocationActionAdditionalOptions(Location $location): ?array {
        $name = strip_tags($location->fullName());
        return [
            'linked' => I18N::translate('%s and the individuals and families that reference it.', $name),
            'linkedPlus' => I18N::translate('%s and the individuals and families that reference it, including parents, siblings, spouses and children of each individual.', $name),
        ];
    }
  
    public function postAddLocationActionHandleOption(
        ClippingsCartAddToCartInterface $target, 
        Location $location, 
        string $option): bool {
    
        switch ($option) {
            case 'linked':
                foreach ($location->linkedIndividuals('_LOC') as $individual) {
                    $target->doAddIndividualToCart($individual);
                }
                foreach ($location->linkedFamilies('_LOC') as $family) {
                    $target->doAddFamilyToCart($family);
                }

                return true;

            case 'linkedPlus':
                foreach ($location->linkedIndividuals('_LOC') as $individual) {
                    $this->addCloseRelativesToCart($target, $individual);
                }

                foreach ($location->linkedFamilies('_LOC') as $family) {            
                    foreach ($family->spouses() as $spouse) {
                        $this->addCloseRelativesToCart($target, $spouse);
                    }

                    foreach ($family->children() as $child) {
                        $this->addCloseRelativesToCart($target, $child);
                    }
                }

                return true;
        }

        return false;
    }
  
    protected function addCloseRelativesToCart(
        ClippingsCartAddToCartInterface $target, 
        Individual $individual): void {
    
        $target->doAddIndividualToCart($individual);
            
        foreach ($individual->spouseFamilies() as $family) {
            $target->doAddFamilyAndChildrenToCart($family);
        }

        foreach ($individual->childFamilies() as $family) {
            $target->doAddFamilyAndChildrenToCart($family);
        }
    }
  
    public function getIndirectLocations(GedcomRecord $record): Collection {
        $ret = new Collection();

        $indirect = boolval($this->getPreference('INDIRECT_LINKS', '0'));
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
  
    ////////////////////////////////////////////////////////////////////////////
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
  
    ////////////////////////////////////////////////////////////////////////////
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
                    $map_lati = ($ll[0] < 0)?"S".str_replace('-', '', (string)$ll[0]):"N".$ll[0];
                    $map_long = ($ll[1] < 0)?"W".str_replace('-', '', (string)$ll[1]):"E".$ll[1];
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

        if (($latitude !== null) && ($longitude !== null)) {
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
    
    ////////////////////////////////////////////////////////////////////////////
  
    private function title1(): string {
        return CommonI18N::locationDataProviders();
    }
  
    private function description1(): string {
        return CommonI18N::mapCoordinates();
    }
  
    private function title2(): string {
        return CommonI18N::placeHistoryDataProviders();
    }
  
    private function description2(): string {
        return CommonI18N::factDataProvidersDescription();
    }
  
    //hook management - generalize?
    //adapted from ModuleController (e.g. listFooters)
    public function getFunctionsPlaceProvidersAction(): ResponseInterface {
        $modules = FunctionsPlaceUtils::modules($this, true);

        $controller = new VestaAdminController($this);
        return $controller->listHooks(
                    $modules,
                    FunctionsPlaceInterface::class,
                    $this->title1(),
                    $this->description1(),
                    true,
                    true);
    }
  
    public function getIndividualFactsTabExtenderProvidersAction(): ResponseInterface {
        $modules = IndividualFactsTabExtenderUtils::modules($this, true);

        $controller = new VestaAdminController($this);
        return $controller->listHooks(
                    $modules,
                    IndividualFactsTabExtenderUtils::moduleSpecificComponentName($this),
                    $this->title2(),
                    $this->description2(),
                    true,
                    true,
                    true);
    }

    public function postFunctionsPlaceProvidersAction(ServerRequestInterface $request): ResponseInterface {
        $controller = new FunctionsPlaceProvidersAction($this);
        return $controller->handle($request);
    }
  
    public function postIndividualFactsTabExtenderProvidersAction(ServerRequestInterface $request): ResponseInterface {
        $controller = new IndividualFactsTabExtenderProvidersAction($this);
        return $controller->handle($request);
    }

    protected function editConfigBeforeFaq() {
        $modules1 = FunctionsPlaceUtils::modules($this, true);

        $url1 = route('module', [
            'module' => $this->name(),
            'action' => 'FunctionsPlaceProviders'
        ]);
    
        $modules2 = IndividualFactsTabExtenderUtils::modules($this, true);

        $url2 = route('module', [
            'module' => $this->name(),
            'action' => 'IndividualFactsTabExtenderProviders'
        ]);

        //cf control-panel.phtml
        ?>
        <div class="card-body">
            <div class="row">
                <div class="col-sm-9">
                    <ul class="fa-ul">
                        <li>
                            <span class="fa-li"><?= view('icons/block') ?></span>
                            <a href="<?= e($url1) ?>">
                                <?= $this->title1() ?>
                            </a>
                            <?= view('components/badge', ['count' => $modules1->count()]) ?>
                            <p class="small text-muted">
                              <?= $this->description1() ?>
                            </p>
                        </li>
                        <li>
                            <span class="fa-li"><?= view('icons/block') ?></span>
                            <a href="<?= e($url2) ?>">
                                <?= $this->title2() ?>
                            </a>
                            <?= view('components/badge', ['count' => $modules2->count()]) ?>
                            <p class="small text-muted">
                              <?= $this->description2() ?>
                            </p>
                        </li>
                    </ul>
                </div>
            </div>
        </div>		

        <?php
    }

}
