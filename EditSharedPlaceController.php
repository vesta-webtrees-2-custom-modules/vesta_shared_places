<?php

declare(strict_types=1);

namespace Cissee\Webtrees\Module\SharedPlaces;

use Cissee\WebtreesExt\ModuleView;
use Fisharebest\Webtrees\Http\Controllers\AbstractEditController;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

//note: for most edit actions, we simply use EditGedcomRecordController!
//cf EditRepositoryController
class EditSharedPlaceController extends AbstractEditController
{
		/** @var string The directory where the module is installed */
		protected $directory;
		
		protected	$moduleName;

		public function __construct(string $directory, string $moduleName) {
			$this->directory = $directory;
			$this->moduleName = $moduleName;
		}
		
    /**
     * Show a form to create a new shared place object.
     *
     * @return Response
     */
    public function createSharedPlace(): Response
    {
        return new Response(ModuleView::make($this->directory, 'modals/create-shared-place', [
						'moduleName' => $this->moduleName,
						'directory' => $this->directory
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
    public function createSharedPlaceAction(Request $request, Tree $tree): JsonResponse
    {
        $name                = $request->get('shared-place-name', '');
        $privacy_restriction = $request->get('privacy-restriction', '');
        $edit_restriction    = $request->get('edit-restriction', '');

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
        return new JsonResponse([
            'id'   => $record->xref(),
            'text' => 'TODO',
            'html' => view('modals/record-created', [
                'title' => I18N::translate('The shared place has been created'),
                'name'  => $record->fullName(),
                'url'   => $record->url(),
            ]),
        ]);
    }
}
