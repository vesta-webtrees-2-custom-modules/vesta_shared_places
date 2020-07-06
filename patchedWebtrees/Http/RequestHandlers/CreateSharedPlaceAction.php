<?php

declare(strict_types=1);

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Cissee\WebtreesExt\Requests;
use Cissee\WebtreesExt\Services\SearchServiceExt;
use Cissee\WebtreesExt\SharedPlace;
use Fisharebest\Webtrees\Factory;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;
use function app;
use function assert;
use function response;
use function view;
      
class SharedPlaceRef {
      
  private $record;
  private $existed;
  private $created;
  private $parent;

  public function record(): SharedPlace {
    return $this->record;
  }

  public function existed(): bool {
    return $this->existed;
  }
  
  public function created(): int {
    return $this->created;
  }

  public function parent(): ?SharedPlaceRef {
    return $this->parent;
  }
  
  public function __construct(SharedPlace $record, bool $existed, int $created, ?SharedPlaceRef $parent) {
    $this->record = $record;
    $this->existed = $existed;
    $this->created = $created;
    $this->parent = $parent;
  }
}
    
/**
 * Process a form to create a new shared place object, and (if necessary) parent objects.
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

        $useHierarchy = Requests::getBool($request, 'useHierarchy');
        $name = Requests::getString($request, 'shared-place-name');
        $govId = Requests::getString($request, 'shared-place-govId'); //cf parameter 'label' in hook in CreateSharedPlaceModal

        // Fix whitespace
        $name = trim(preg_replace('/\s+/', ' ', $name));

        if ($useHierarchy) {
          $ref = $this->createIfRequired($name, $govId, $tree);
          $record = $ref->record();
          
          if ($ref->created() === 0) {
            return response([
                    'html' => view('modals/record-created', [
                        'title' => I18N::translate('The shared place %s already exists.', $record->fullName()),
                        'name'  => $record->fullName(),
                        'url'   => $record->url(),
                    ]),
                ]);
          } else {
            $html = '';
            if ($ref->created() === 2) {
              $html = ' ' . I18N::translate(' (Note: A parent shared place has also been created)');
            } else if ($ref->created() > 2) {
               $html = ' ' . I18N::translate(' (Note: %s parent shared places have also been created)', $ref->created());
            }
          
            // id and text are for select2 / autocomplete
            // html is for interactive modals
            return response([
                    'id'   => $record->xref(),
                    'text' => view('selects/location', [
                        'location' => $record,
                    ]),
                    'html' => view('modals/record-created', [
                        'title' => I18N::translate('The shared place %s has been created.', $record->fullName()),
                        'name'  => $record->fullName() . $html,
                        'url'   => $record->url(),
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
    
    //useHierarchy === true
    public function createIfRequired(
            string $placeGedcomName,
            string $govId,
            Tree $tree,
            bool $simulate = false): SharedPlaceRef {
      
      $parts = explode(Gedcom::PLACE_SEPARATOR, $placeGedcomName);
      $tail = implode(Gedcom::PLACE_SEPARATOR, array_slice($parts, 1));
      $head = reset($parts);

      //if the place exists (with hierarchy), just return
      $searchService = app(SearchServiceExt::class);
      $sharedPlaces = $searchService->searchLocations(array($tree), array("1 NAME " . $head . "\n"));
      foreach ($sharedPlaces as $sharedPlace) {
        if ($sharedPlace->matches($placeGedcomName)) {
          return new SharedPlaceRef($sharedPlace, true, 0, null);
        }
      }      
      
      //otherwise create (including missing parents)
      
      $ref = null;
      if ($tail !== '') {
        $ref = $this->createIfRequired($tail, '', $tree, $simulate);
      }
      
      $gedcom = "0 @@ _LOC\n1 NAME " . $head;

      if ($govId != '') {
        $gedcom .= "\n1 _GOV " . $govId;
      }
      
      if ($ref !== null) {
        $gedcom .= "\n1 _LOC @" . $ref->record()->xref() . "@";
        $gedcom .= "\n2 TYPE POLI";
      }

      if (!$simulate) {
        $record = $tree->createRecord($gedcom); //returns GedcomRecord
      }
      $newXref = 'NEXT_XREF_' . Uuid::uuid4()->toString();
      if (!$simulate) {
        $newXref = $record->xref();
      }
      $record = Factory::location()->make($newXref, $tree, $gedcom); //we need Location for proper names!
      
      $count = 1;
      if ($ref !== null) {
        $count += $ref->created();
      }
      return new SharedPlaceRef($record, false, $count, $ref);
    }
}
