<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Cissee\Webtrees\Module\SharedPlaces\SharedPlacesModule;
use Cissee\WebtreesExt\Requests;
use Cissee\WebtreesExt\Services\SearchServiceExt;
use Cissee\WebtreesExt\SharedPlace;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Vesta\Model\PlaceStructure;
use function app;
use function response;
use function view;

/**
 * Process a form to create a new shared place object, and (if necessary) parent objects.
 */
class CreateSharedPlaceAction implements RequestHandlerInterface {

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface {
        $tree = Validator::attributes($request)->tree();

        $params = (array) $request->getParsedBody();
        $useHierarchy = (bool) ($params['useHierarchy'] ?? false);
        $name = $params['shared-place-name'];
        $govId = $params['shared-place-govId'] ?? '';

        // Fix whitespace
        $name = trim(preg_replace('/\s+/', ' ', $name));

        if ($useHierarchy) {
            $ref = $this->createIfRequired($name, $govId, $tree);
            $record = $ref->record();

            if ($ref->created() === 0) {
                return response([
                    'html' => view('modals/record-created', [
                        'title' => I18N::translate('The shared place %s already exists.', $record->fullName()),
                        'name' => $record->fullName(),
                        'url' => $record->url(),
                    ]),
                ], 409);
            } else {
                $html = '';
                if ($ref->created() === 2) {
                    $html = ' ' . I18N::translate(' (Note: A higher-level shared place has also been created)');
                } else if ($ref->created() > 2) {
                    $html = ' ' . I18N::translate(' (Note: %s higher-level shared places have also been created)', $ref->created() - 1);
                }

                // value and text and title are for autocomplete
                // html is for interactive modals
                return response([
                    'value' => '@' . $record->xref() . '@',
                    'text' => view('selects/location', [
                        'location' => $record,
                    ]),
                    //cf TomSelectSharedPlace, in this case same as text!
                    'title' => view('selects/location', [
                        'location' => $record,
                    ]),
                    'html' => view('modals/record-created', [
                        'title' => I18N::translate('The shared place %s has been created.', $record->fullName()),
                        'name' => $record->fullName() . $html,
                        'url' => $record->url(),
                    ]),
                ]);
            }
        }

        //else (no hierarchy)

        $privacy_restriction = Requests::getString($request, 'privacy-restriction');
        $edit_restriction = Requests::getString($request, 'edit-restriction');

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
        $record = Registry::locationFactory()->make($record->xref(), $tree); //we need Location for proper names!
        //FlashMessages::addMessage(I18N::translate('The shared place %s has been created.', $name), 'info');
        // id and text are for select2 / autocomplete
        // html is for interactive modals
        return response([
            'id' => $record->xref(),
            'text' => view('selects/location', [
                'location' => $record,
            ]),
            //cf TomSelectSharedPlace, in this case same as text!
            'title' => view('selects/location', [
                'location' => $record,
            ]),
            'html' => view('modals/record-created', [
                'title' => I18N::translate('The shared place %s has been created.', $name),
                'name' => $record->fullName(),
                'url' => $record->url(),
            ]),
        ]);
    }

    public function createIfRequired(
        string $placeGedcomName,
        string $govId,
        Tree $tree,
        bool $simulate = false,
        ?SharedPlacesModule $enhanceWithGlobalData = null,
        bool $onlyIfGlobalDataAvailable = false): ?SharedPlaceRef {

        $parts = SharedPlace::placeNameParts($placeGedcomName);
        $tail = SharedPlace::placeNamePartsTail($parts);
        $head = reset($parts);

        //hacky - should we even support this here?
        if ($enhanceWithGlobalData !== null) {
            $useHierarchy = boolval($enhanceWithGlobalData->getPreference('USE_HIERARCHY', '1'));

            if (!$useHierarchy) {
                $head = $placeGedcomName;
                $tail = '';
            }
        }

        //if the place exists (with hierarchy), just return
        /* @var $searchService SearchServiceExt */
        $searchService = app(SearchServiceExt::class);
        $sharedPlace = $searchService->searchLocationsInPlace(new Place($placeGedcomName, $tree))->first();
        if ($sharedPlace !== null) {
            return new SharedPlaceRef($sharedPlace, true, 0, null);
        }

        //otherwise create (including missing parents)

        $gedcom = "0 @@ _LOC\n1 NAME " . $head;

        $enhancedWithGlobalData = false;

        if ($govId != '') {
            $gedcom .= "\n1 _GOV " . $govId;
        } else if ($enhanceWithGlobalData !== null) {
            $plac2GovSupporters = $enhanceWithGlobalData->getPlac2GovSupporters($tree);

            if (sizeof($plac2GovSupporters) > 0) {
                foreach ($plac2GovSupporters as $plac2GovSupporter) {
                    $gov = $plac2GovSupporter->plac2gov(PlaceStructure::fromName($placeGedcomName, $tree));
                    if ($gov !== null) {
                        $gedcom .= "\n1 _GOV " . $gov->getId();
                        $enhancedWithGlobalData = true;
                        break;
                    }
                }
            }
        }

        if ($enhanceWithGlobalData !== null) {
            $ll = $enhanceWithGlobalData->getLatLon($placeGedcomName);

            if ($ll !== null) {
                $map_lati = ($ll[0] < 0) ? "S" . str_replace('-', '', (string) $ll[0]) : "N" . $ll[0];
                $map_long = ($ll[1] < 0) ? "W" . str_replace('-', '', (string) $ll[1]) : "E" . $ll[1];
                $gedcom .= "\n1 MAP\n2 LATI " . $map_lati . "\n2 LONG " . $map_long;
                $enhancedWithGlobalData = true;
            }
        }

        if ($onlyIfGlobalDataAvailable && !$enhancedWithGlobalData) {
            return null;
        }

        /////////////////////////////////////////////////////////////////////////
        //start to actually change something!

        $ref = null;
        if ($tail !== '') {
            //missing parents have to be created regardless of their own $onlyIfGlobalDataAvailable!
            $ref = $this->createIfRequired($tail, '', $tree, $simulate, $enhanceWithGlobalData);
        }

        if ($ref !== null) {
            $gedcom .= "\n1 _LOC @" . $ref->record()->xref() . "@";
            $gedcom .= "\n2 TYPE POLI";
        }

        if (!$simulate) {
            $record = $tree->createRecord($gedcom); //returns GedcomRecord
        }
        $newXref = 'NX_' . CreateSharedPlaceAction::generateRandomString(16);
        if (!$simulate) {
            $newXref = $record->xref();
        }

        //we need Location for proper names!
        //and we must check() in order to update place links
        /** @var SharedPlace $record */
        
        $record = Registry::locationFactory()->make($newXref, $tree, $gedcom);
        $record->check();

        $count = 1;
        if ($ref !== null) {
            $count += $ref->created();
        }
        return new SharedPlaceRef($record, false, $count, $ref);
    }

    //Uuid::uuid4() is to long for XREF (max length 20)
    //https://stackoverflow.com/questions/4356289/php-random-string-generator
    public static function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

}
