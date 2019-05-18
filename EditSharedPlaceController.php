<?php

declare(strict_types=1);

namespace Cissee\Webtrees\Module\SharedPlaces;

use Fisharebest\Webtrees\Http\Controllers\AbstractEditController;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Cissee\WebtreesExt\Requests;


//note: for most edit actions, we simply use EditGedcomRecordController!
//cf EditRepositoryController
class EditSharedPlaceController extends AbstractEditController {

  protected $moduleName;

  public function __construct(string $moduleName) {
    $this->moduleName = $moduleName;
  }

  /**
   * Show a form to create a new shared place object.
   *
   * @return Response
   */
  public function createSharedPlace(ServerRequestInterface $request): ResponseInterface {
    $sharedPlaceName = Requests::getString($request, 'shared-place-name');
    
    return response(view($this->moduleName . '::modals/create-shared-place', [
                'moduleName' => $this->moduleName,
                'sharedPlaceName' => $sharedPlaceName,
    ]));
  }

  /**
   * Process a form to create a new shared place object.
   *
   * @param Request $request
   * @param Tree    $tree
   *
   * @return JsonResponse
   */
  public function createSharedPlaceAction(ServerRequestInterface $request, Tree $tree): ResponseInterface {
    $name = Requests::getString($request, 'shared-place-name');
    $privacy_restriction = Requests::getString($request, 'privacy-restriction');
    $edit_restriction = Requests::getString($request, 'edit-restriction');

    // Fix whitespace
    $name = trim(preg_replace('/\s+/', ' ', $name));

    $gedcom = "0 @@ _LOC\n1 NAME " . $name;

    if (in_array($privacy_restriction, [
                'none',
                'privacy',
                'confidential',
            ])) {
      $gedcom .= "\n1 RESN " . $privacy_restriction;
    }

    if (in_array($edit_restriction, ['locked'])) {
      $gedcom .= "\n1 RESN " . $edit_restriction;
    }

    $record = $tree->createRecord($gedcom);

    // id and text are for select2 / autocomplete //[RC] which we currently do not need
    // html is for interactive modals
    return response([
        'id' => $record->xref(),
        'text' => 'TODO',
        'html' => view('modals/record-created', [
            'title' => I18N::translate('The shared place has been created'),
            'name' => $record->fullName(),
            'url' => $record->url(),
        ]),
    ]);
  }

}
