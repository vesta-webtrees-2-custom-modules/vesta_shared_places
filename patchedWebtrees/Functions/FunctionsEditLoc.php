<?php

namespace Cissee\WebtreesExt\Functions;

use Cissee\WebtreesExt\Http\RequestHandlers\CreateSharedPlaceModal;
use Cissee\WebtreesExt\SharedPlace;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Factory;
use Fisharebest\Webtrees\Functions\FunctionsEdit;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\Html;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Ramsey\Uuid\Uuid;
use Vesta\Hook\HookInterfaces\GovIdEditControlsInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsUtils;
use function route;
use function view;

//simplified from FunctionsEdit for _LOC.NAME, _LOC.MAP, ,_LOC._GOV, and _LOC._LOC.TYPE
//FunctionsEdit is too messy, should be refactored! 
class FunctionsEditLoc {
  
    /** @var string[] Possible values for the location hierarchical relationship types */
    private const LOC_TYPE = [
        'POLI',
        'RELI',
        'GEOG',
        'CULT',
    ];
    
  public static function createAddFormLoc(GedcomRecord $record, Tree $tree, $fact): void
  {
      if ($fact === '_LOC') {
        echo self::addSimpleTagWithGedcomRecord($record, $tree, '1 ' . $fact, '_LOC');
      } else {
        echo self::addSimpleTagWithGedcomRecord($record, $tree, '1 ' . $fact, 'TYPE');
      }

      //[RC][added]
      //-- handle the special MAP case for level 1 maps (under _LOC)
      if ($fact === 'MAP') {
          //note: if we'd use standard FunctionsEdit::createAddForm, we'd have to adjust
          //Config::emptyFacts() and remove MAP there:
          //
          //old comment was:
          //yes, it's empty, but it has a substructure, so it's different from those other empty facts
          //(adjusted because we don't want to show 'yes' here via FunctionsPrint::formatFactDate, when adding a fact)
        
          //initialize with N0/E0 to prevent collapse (hacky)
          echo FunctionsEdit::addSimpleTag($tree, '2 LATI N0');
          echo FunctionsEdit::addSimpleTag($tree, '2 LONG E0');
      } else if ($fact === '_LOC') {
          echo self::addSimpleTagWithGedcomRecord($record, $tree, '2 TYPE POLI', '_LOC');
      } else if ($fact === 'TYPE') {
          echo self::addSimpleTagWithGedcomRecord($record, $tree, '2 _GOVTYPE', 'TYPE');
      }
      
      //_GOV:
      //note: if we'd use standard FunctionsEdit::createAddForm, we'd have to adjust
      //Config::nonDateFacts() and add _GOV there
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
          
          //[RC] adjusted, hacky
          if ($label === 'TYPE') {
            $label = '_LOC:TYPE';
          } else if ($label === '_LOC') {
            $label = '_LOC:_LOC';
          } else if ($label === '_LOC:TYPE') {
            $label = '_LOC:_LOC:TYPE';
          }
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
    // field value
    $islink = (bool) preg_match('/^@[^#@][^@]*@$/', $value);
    if ($islink) {
        $value = trim($value, '@');
    }

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
    
    if ($fact === '_LOC') {
      $islink = true;
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
      case '_LOC':
        //cf SHARED_NOTE, but use special vesta modal!
        $html .=
                '<div class="input-group">' .
                '<div class="input-group-prepend">' .
                '<button class="btn btn-secondary" type="button" data-toggle="modal" data-target="#wt-ajax-modal-vesta" data-href="' . e(route(CreateSharedPlaceModal::class, ['tree' => $tree->name()])) . '" data-select-id="' . $id . '" title="' . I18N::translate('Create a shared place') . '">' .
                '' . view('icons/add') . '<' .
                '/button>' .
                '</div>' .
                view('components/select-location', ['id' => $id, 'name' => $name, 'location' => Factory::location()->make($value, $tree), 'tree' => $tree]) .
                '</div>';
        break;
      case 'LATI':
        //same as original FunctionsEdit
        $html .= '<input class="form-control" type="text" id="' . $id . '" name="' . $name . '" value="' . e($value) . '" oninput="valid_lati_long(this, \'N\', \'S\')">';
        break;
      case 'LONG':
        //same as original FunctionsEdit
        $html .= '<input class="form-control" type="text" id="' . $id . '" name="' . $name . '" value="' . e($value) . '" oninput="valid_lati_long(this, \'E\', \'W\')">';
        break;
      //_LOC.TYPE
      case 'TYPE':
        if ($level === '2') {
          //-- Build the selector for the Location 'TYPE' Fact
          $html .= '<select name="text[]">';
          $selectedValue = strtoupper($value);
          if (!array_key_exists($selectedValue, self::getLocationRelationshipTypes())) {
              $html .= '<option selected value="' . e($value) . '" >' . e($value) . '</option>';
          }
          foreach (self::getLocationRelationshipTypes() + [] as $typeName => $typeValue) {
              $html .= '<option value="' . $typeName . '" ';
              if ($selectedValue === $typeName) {
                  $html .= 'selected';
              }
              $html .= '>' . $typeValue . '</option>';
          }
          $html .= '</select>';
        } else {
          //level 1: type of location!
          $html .= '<input class="form-control" type="text" id="' . $id . '" name="' . $name . '" value="' . e($value) . '">';
        }
        break;     
      case '_GOVTYPE':
        $htmlGovtype = '';
        //hooked?
        $additionalControls = GovIdEditControlsUtils::accessibleModules($tree, Auth::user())
              ->map(function (GovIdEditControlsInterface $module) use ($value, $id, $name) {
                return $module->govTypeIdEditControl(($value === '')?null:$value, $id, $name);
              })
              ->toArray();

        foreach ($additionalControls as $additionalControl) {
          $htmlGovtype = $additionalControl->getMain();
          //apparently handled properly
          View::push('javascript');
          echo $additionalControl->getScript();
          View::endpush();
        }
    
        if ($htmlGovtype === '') {
          $htmlGovtype = '<input class="form-control" type="text" id="' . $id . '" name="' . $name . '" value="' . e($value) . '">';          
        }
        $html .= $htmlGovtype;
        break;
      default:
        //#17
        ////may be a custom tag, so  just allow editing
        //throw new Exception("unexpected tag: " . $fact);
        $html .= '<input class="form-control" type="text" id="' . $id . '" name="' . $name . '" value="' . e($value) . '">';
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
    $additionalControls = GovIdEditControlsUtils::accessibleModules($tree, Auth::user())
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
  
  /**
   * A list of all possible values for 0 _LOC/1 _LOC/2 TYPE
   *
   * @return string[]
   */
  public static function getLocationRelationshipTypes(): array
  {
     $values = array_map(static function (string $keyword): string {
         return self::getLocationRelationshipTypeValue($keyword);
     }, array_combine(self::LOC_TYPE, self::LOC_TYPE));

     uasort($values, '\Fisharebest\Webtrees\I18N::strcasecmp');

     return $values;
  }

    /**
     * Translate the value for 0 _LOC/1 _LOC/2 TYPE
     *
     * @param string $type
     *
     * @return string
     */
    public static function getLocationRelationshipTypeValue(string $type): string
    {
        switch ($type) {
            case 'POLI':
                /* I18N: Type of hierarchical relationship between locations */
                return I18N::translate('administrative');
            case 'RELI':
                /* I18N: Type of hierarchical relationship between locations */
                return I18N::translate('religious');
            case 'GEOG':
                /* I18N: Type of hierarchical relationship between locations */
                return I18N::translate('geographical');
            case 'CULT':
                /* I18N: Type of hierarchical relationship between locations */
                return I18N::translate('cultural');
            default:
                /* I18N: Type of hierarchical relationship between locations */
                return I18N::translate('other');
        }
    }
}
