<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Cissee\WebtreesExt\Requests;
use Fisharebest\Webtrees\Factory;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function assert;
use function response;
use function view;

/**
 * Process a form to create a new shared place object.
 */
class CreateSharedPlaceAction implements RequestHandlerInterface
{
    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = $request->getAttribute('tree');
        assert($tree instanceof Tree);

        $name = Requests::getString($request, 'shared-place-name');
        $govId = Requests::getString($request, 'shared-place-govId'); //cf parameter 'label' in hook in CreateSharedPlaceModal
        $privacy_restriction = Requests::getString($request, 'privacy-restriction');
        $edit_restriction = Requests::getString($request, 'edit-restriction');

        // Fix whitespace
        $name = trim(preg_replace('/\s+/', ' ', $name));

        $gedcom = "0 @@ _LOC\n1 NAME " . $name;

        if ($govId != '') {
          $gedcom .= "\n1 _GOV " . $govId;
        }

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

        $record = $tree->createRecord($gedcom); //returns GedcomRecord
        $record = Factory::location()->make($record->xref(), $tree); //we need Location for proper names!
        
        //FlashMessages::addMessage(I18N::translate('The shared place %s has been created.', $name), 'info');

        // id and text are for select2 / autocomplete
        // html is for interactive modals
        return response([
                'id'   => $record->xref(),
                'text' => view('selects/location', [
                    'location' => $record,
                ]),
                'html' => view('modals/record-created', [
                    'title' => I18N::translate('The shared place %s has been created.', $name),
                    'name'  => $record->fullName(),
                    'url'   => $record->url(),
                ]),
            ]);
    }
}
