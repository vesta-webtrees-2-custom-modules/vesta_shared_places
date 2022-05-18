<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Cissee\WebtreesExt\Functions\FunctionsPrintExt;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsUtils;
use function response;
use function view;

/**
 * Show a form to create a new shared place object.
 */
class CreateSharedPlaceModal implements RequestHandlerInterface
{
    
    protected $module;
    protected $moduleName;

    function __construct($module) {
      $this->module = $module;
      $this->moduleName = $module->name();
    }
  
    /**
     * Show a form to create a new shared place object.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $sharedPlaceName = Validator::queryParams($request)->string('shared-place-name', '');
        $selector = Validator::queryParams($request)->string('shared-place-name-selector', '');
        
        //requires modal placeholder in SharedPlacesListController.sharedPlacesList(), uargh
        //also requires modal placeholder in edit fact! meh.
        //also requires modal placeholder in SharedPlacesModule.hFactsTabGetAdditionalEditControls(),
        //handled via hFactsTabRequiresModalVesta!
        
        $additionalControls = GovIdEditControlsUtils::accessibleModules($tree, Auth::user())
                ->map(function (GovIdEditControlsInterface $module) use ($sharedPlaceName) {
                  return $module->govIdEditControl(
                          null, 
                          'shared-place-govId', 
                          'shared-place-govId', 
                          $sharedPlaceName, 
                          '#shared-place-name', //cf shared-place-fields.phtml
                          true, 
                          true);
                })
                ->toArray();

        $useHierarchy = boolval($this->module->getPreference('USE_HIERARCHY', '1'));
        
        $requiredfactsStr = $this->module->getPreference('_LOC_FACTS_REQUIRED', '');
        $requiredfacts = FunctionsPrintExt::adjust(preg_split("/[, ]+/", $requiredfactsStr, -1, PREG_SPLIT_NO_EMPTY));
      
        return response(view($this->moduleName . '::modals/create-shared-place', [
                    'moduleName' => $this->moduleName,
                    'useHierarchy' => $useHierarchy,
                    'sharedPlaceName' => $sharedPlaceName,
                    'selector' => $selector,
                    'additionalControls' => $additionalControls,
                    'requiredfacts' => $requiredfacts,
                    'tree' => $tree,
        ]));
    }
}
