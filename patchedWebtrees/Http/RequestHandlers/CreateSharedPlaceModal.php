<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Cissee\WebtreesExt\Requests;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsUtils;
use function assert;
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
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $sharedPlaceName = Requests::getString($request, 'shared-place-name');
        
        //requires modal placeholder in SharedPlacesListController.sharedPlacesList(), uargh
        //also requires modal placeholder in edit fact!
        //also requires modal placeholder in SharedPlacesModule.hFactsTabGetAdditionalEditControls(),
        //handled via hFactsTabRequiresModalVesta!
        $additionalControls = GovIdEditControlsUtils::accessibleModules($tree, Auth::user())
                ->map(function (GovIdEditControlsInterface $module) use ($sharedPlaceName) {
                  return $module->govIdEditControl(null, 'shared-place-govId', 'shared-place-govId', $sharedPlaceName, true, true);
                })
                ->toArray();

        $useHierarchy = boolval($this->module->getPreference('USE_HIERARCHY', '1'));
        
        return response(view($this->moduleName . '::modals/create-shared-place', [
                    'moduleName' => $this->moduleName,
                    'useHierarchy' => $useHierarchy,
                    'sharedPlaceName' => $sharedPlaceName,
                    'additionalControls' => $additionalControls,
                    'tree' => $tree,
        ]));
    }
}
