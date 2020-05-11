<?php

namespace Cissee\WebtreesExt\Functions;

use Cissee\WebtreesExt\SharedPlace;
use Exception;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Functions\FunctionsEdit;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\Html;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Ramsey\Uuid\Uuid;
use Vesta\Hook\HookInterfaces\GovIdEditControlsInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsUtils;
use function route;
use function view;

//simplified from FunctionsEdit for _LOC.NAME, _LOC.MAP, and _LOC._GOV
//FunctionsEdit is too messy, should be refactored! 
class FunctionsEditLoc {
  
  public static function createAddFormLoc(GedcomRecord $record, Tree $tree, $fact): void
  {
      echo self::addSimpleTagWithGedcomRecord($record, $tree, '1 ' . $fact);

      //[RC][added]
      //-- handle the special MAP case for level 1 maps (under _LOC)
      if ($fact === 'MAP') {
          //initialize with N0/E0 to prevent collapse (hacky)
          echo FunctionsEdit::addSimpleTag($tree, '2 LATI N0');
          echo FunctionsEdit::addSimpleTag($tree, '2 LONG E0');
      }
  }
    
  public static function createEditFormLoc(Fact $fact): void
  {
      $record = $fact->record();
      $tree   = $record->tree();

      $level0type = $record::RECORD_TYPE;

      $stack       = [];
      $gedlines    = explode("\n", $fact->gedcom());
      $count       = count($gedlines);
      $i           = 0;

      // Loop on existing tags :
      while ($i < $count) {
          $fields = explode(' ', $gedlines[$i], 3);
          $level  = (int) $fields[0];
          $type   = $fields[1] ?? '';
          $text   = $fields[2] ?? '';

          // Keep track of our hierarchy, e.g. 1=>BIRT, 2=>PLAC, 3=>FONE
          $stack[$level] = $type;
          // Merge them together, e.g. BIRT:PLAC:FONE
          $label = implode(':', array_slice($stack, 0, $level));

          // Merge text from continuation lines
          while ($i + 1 < $count && preg_match('/^' . ($level + 1) . ' CONT ?(.*)/', $gedlines[$i + 1], $cmatch) > 0) {
              $text .= "\n" . $cmatch[1];
              $i++;
          }

          $subrecord = $level . ' ' . $type . ' ' . $text;

          echo self::addSimpleTagWithGedcomRecord($record, $tree, $subrecord, $level0type, GedcomTag::getLabel($label, $record));
          $i++;
      }
  }

  public static function addSimpleTagWithGedcomRecord(?GedcomRecord $record, Tree $tree, $tag, $upperlevel = '', $label = ''): string
  {
    $parts = explode(' ', $tag, 3);
    $level = $parts[0] ?? '';
    $fact  = $parts[1] ?? '';
    $value = $parts[2] ?? '';

    if ($level === '0') {
        // Adding a new fact.
        if ($upperlevel) {
            $name = $upperlevel . '_' . $fact;
        } else {
            $name = $fact;
        }
    } else {
        // Editing an existing fact.
        $name = 'text[]';
    }

    $id = $fact . Uuid::uuid4()->toString();
    $islink = false;

    $row_class = 'form-group row';

    $html = '';
    $html .= '<div class="' . $row_class . '">';
    $html .= '<label class="col-sm-3 col-form-label" for="' . $id . '">';

    // tag name
    if ($label) {
        $html .= $label;
    } elseif ($upperlevel) {
        $html .= GedcomTag::getLabel($upperlevel . ':' . $fact);
    } else {
        $html .= GedcomTag::getLabel($fact);
    }

    // tag level
    if ($level !== '0') {
        $html .= '<input type="hidden" name="glevels[]" value="' . $level . '">';
        $html .= '<input type="hidden" name="islink[]" value="' . $islink . '">';
        $html .= '<input type="hidden" name="tag[]" value="' . $fact . '">';
    }
    $html .= '</label>';

    // value
    $html .= '<div class="col-sm-9">';

    switch ($fact) {
      case 'NAME':
        //just like PLAC
        $html .= '<div class="input-group">';        
        $html .= '<input ' . Html::attributes([
                'autocomplete'          => 'off',
                'class'                 => 'form-control',
                'id'                    => $id,
                'name'                  => $name,
                'value'                 => $value,
                'type'                  => 'text',
                'data-autocomplete-url' => route('autocomplete-place', ['tree'  => $tree->name(), 'query' => 'QUERY']),
            ]) . '>';

        //except without coordinates
        //$html .= view('edit/input-addon-coordinates', ['id' => $id]);
        
        $html .= view('edit/input-addon-help', ['fact' => 'PLAC']);
        $html .= '</div>';
        break;
      case 'MAP':
        //standard empty field
        $html .= '<input type="hidden" id="' . $id . '" name="' . $name . '" value="' . $value . '">';
        break;
      case '_GOV':
        //special
        $html .= self::htmlForGov($record, $tree, $id, $name, $value);
        break;
      case 'LATI':
        //same as original FunctionsEdit
        $html .= '<input class="form-control" type="text" id="' . $id . '" name="' . $name . '" value="' . e($value) . '" oninput="valid_lati_long(this, \'N\', \'S\')">';
        break;
      case 'LONG':
        //same as original FunctionsEdit
        $html .= '<input class="form-control" type="text" id="' . $id . '" name="' . $name . '" value="' . e($value) . '" oninput="valid_lati_long(this, \'E\', \'W\')">';
        break;

      default:
        throw new Exception("unexpected tag: " . $fact);
    }

    $html .= '</div></div>';

    return $html;
  }

  //[RC] added
  protected static function htmlForGov(?GedcomRecord $record, Tree $tree, $id, $name, $value) {
    $placeName = '';
    if ($record !== null) {
      if ($record instanceof SharedPlace) {
        $sharedPlace = $record;
        foreach ($sharedPlace->namesNN() as $nameNN) {
          //first name wins
          if ($placeName === '') {
            $placeName = $nameNN;
          }
        }
      }
    }

    $html = '';
    //hooked?
    $additionalControls = GovIdEditControlsUtils::accessibleModules(null, $tree, Auth::user())
          ->map(function (GovIdEditControlsInterface $module) use ($value, $id, $name, $placeName) {
            return $module->govIdEditControl(($value === '')?null:$value, $id, $name, $placeName, false, false);
          })
          ->toArray();

    foreach ($additionalControls as $additionalControl) {
      $html .= $additionalControl->getMain();
      //apparently handled properly
      View::push('javascript');
      echo $additionalControl->getScript();
      View::endpush();
    }

    if ($html !== '') {
      return $html;
    }

    return '<input class="form-control" type="text" id="' . $id . '" name="' . $name . '" value="' . e($value) . '">';
  }
}
