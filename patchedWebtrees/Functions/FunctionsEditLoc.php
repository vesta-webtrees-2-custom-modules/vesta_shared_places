<?php

namespace Cissee\WebtreesExt\Functions;

use Cissee\Webtrees\Module\SharedPlaces\SharedPlacesModule_20;
use Cissee\WebtreesExt\Http\RequestHandlers\CreateSharedPlaceModal_20;
use Cissee\WebtreesExt\SharedPlace;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\Functions\FunctionsEdit;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\GedcomTag;
use Fisharebest\Webtrees\Html;
use Fisharebest\Webtrees\Http\RequestHandlers\AutoCompletePlace;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\LocalizationService;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Vesta\Hook\HookInterfaces\GovIdEditControlsInterface;
use Vesta\Hook\HookInterfaces\GovIdEditControlsUtils;
use function app;
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
          echo FunctionsEdit::addSimpleTag($tree, '2 DATE');
      } else if ($fact === 'TYPE') {
          echo self::addSimpleTagWithGedcomRecord($record, $tree, '2 _GOVTYPE', 'TYPE');
          echo FunctionsEdit::addSimpleTag($tree, '2 DATE');
      } else if ($fact === 'NAME') {
          echo FunctionsEdit::addSimpleTag($tree, '2 DATE');
          
          //Issue #77
          echo FunctionsEdit::addSimpleTag($tree, '2 LANG');
      }
      
      //_GOV:
      //note: if we'd use standard FunctionsEdit::createAddForm, we'd have to adjust
      //Config::nonDateFacts() and add _GOV there
  }
    
  public static function createEditFormLoc(Fact $fact): void
  {
      $record = $fact->record();
      $tree   = $record->tree();
      
      $tags = [];
        
      $level0type = $record->tag();
      $level1type = $fact->getTag();

      $stack       = [];
      $gedlines    = explode("\n", $fact->gedcom());
      $count       = count($gedlines);
      $i           = 0;
      $add_date    = true;

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

          $tags[] = $type;
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
      
      self::insertMissingSubtags($record, $tree, $level1type, $tags);
  }

  public static function insertMissingSubtags(?GedcomRecord $record, Tree $tree, $level1tag, $tags): void {
    
    if ($level1tag === 'TYPE') {
      if (!in_array('_GOVTYPE', $tags, true)) {
        echo self::addSimpleTagWithGedcomRecord($record, $tree, '2 _GOVTYPE', 'TYPE');
      }
    } 
    if (($level1tag === '_LOC') || ($level1tag === 'TYPE') || ($level1tag === 'NAME')) {
      if (!in_array('DATE', $tags, true)) {
        echo FunctionsEdit::addSimpleTag($tree, '2 DATE');
      }
    }
    
    //Issue #77
    if ($level1tag === 'NAME') {
      if (!in_array('LANG', $tags, true)) {
        echo FunctionsEdit::addSimpleTag($tree, '2 LANG');
      }
    }
  }
  
  public static function addSimpleTagWithGedcomRecord(
          ?GedcomRecord $record, 
          Tree $tree, 
          $tag, 
          $upperlevel = '', 
          $label = ''): string
  {
    
    //[RC] gah so hacky - we need this for LATI/LONG because we write PLAC!
    // Some form fields need access to previous form fields.
    static $previous_ids = [
        'PLAC' => '',
    ];
        
    $parts = explode(' ', $tag, 3);
    $level = $parts[0] ?? '';
    $fact  = $parts[1] ?? '';
    $value = $parts[2] ?? '';
    
    $upperlevelDOM = str_replace(':','_',$upperlevel);
          
    if ($level === '0') {
        // Adding a new fact.
        if ($upperlevel) {
            //[RC] adjustment for BIRT:PLAC etc, leading to names like 'BIRT_PLAC__LOC'
            $name = $upperlevelDOM . '_' . $fact;
        } else {
            $name = $fact;
        }
    } else {
        // Editing an existing fact.
        $name = 'text[]';
    }

    //[RC] added
    if ($upperlevel) {
        //[RC] adjustment for BIRT:PLAC etc, leading to names like 'BIRT_PLAC__LOC'
        $name2 = $upperlevelDOM . '_' . $fact;
    } else {
        $name2 = $fact;
    }
          
    //for _LOC under PLAC, upperlevel sometimes (existing tag) given as INDI/FAM, inconsistently, probably not worth investigating
    if (!Str::endsWith($upperlevel, 'PLAC')) {
      $upperlevelDOM .= '_PLAC';
    }
  
    $id = $fact . Uuid::uuid4()->toString();    
    $previous_ids[$fact] = $id;
    
    // field value
    $islink = (bool) preg_match('/^@[^#@][^@]*@$/', $value);
    if ($islink) {
        $value = trim($value, '@');
    }

    $row_class = 'form-group row';
    switch ($fact) {
        case 'DATA':
        case 'MAP':
            // These GEDCOM tags should have no data, just child tags.
            if ($value === '') {
                $row_class .= ' d-none';
            }
            break;
        case 'LATI':
        case 'LONG':
            // Indicate that this row is a child of a previous row, so we can expand/collapse them.
            $row_class .= ' child_of_' . $previous_ids['PLAC'];
            if ($value === '') {
                $row_class .= ' collapse';
            }
            break;
    }
        
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
        //just like original PLAC
        $html .= '<div class="input-group">';        
        $html .= '<input ' . Html::attributes([
                'autocomplete'          => 'off',
                'class'                 => 'form-control',
                'id'                    => $id,
                'name'                  => $name,
                'value'                 => $value,
                'type'                  => 'text',
                'data-autocomplete-url' => route(AutoCompletePlace::class, ['tree'  => $tree->name()]),
            ]) . '>';

        //except without coordinates
        //$html .= view('edit/input-addon-coordinates', ['id' => $id]);
        
        //and with non-standard help text
        //$html .= view('edit/input-addon-help', ['fact' => 'PLAC']);
        $module = app(SharedPlacesModule_20::class);
        $useHierarchy = boolval($module->getPreference('USE_HIERARCHY', '1'));
        if ($useHierarchy) {
          $html .= FunctionsPrintExtHelpLink::inputAddonHelp($module->name(), 'PLAC');
        } else {
          $html .= FunctionsPrintExtHelpLink::inputAddonHelp($module->name(), 'PLAC_CSV');
        }        
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
        
        /** @var SharedPlace $location */
        $location = Registry::locationFactory()->make($value, $tree);
        $locationName = '';
        if ($location !== null) {
          $locationName = $location->primaryPlace()->gedcomName();
        }
        
        $selector = '';
        $selectorForDate = '';
        if (Str::endsWith($upperlevel, 'PLAC') || ($upperlevel === 'INDI') || ($upperlevel === 'FAM')) {          
          //we have to disambiguate here ('[id]'): the input element is duplicated, apparently by the typeahead functionality, bah
          $selector = '[id][data-vesta-name="'.$upperlevelDOM.'"]';
          $selectorForDate = '[id][data-vesta-name="'.str_replace('PLAC','DATE',$upperlevelDOM).'"]';
          
          /*
          error_log("got selector!".$selector);
          error_log("got selectorForDate!".$selectorForDate);
          error_log("upperlevelDOM".$upperlevelDOM);
          */
        }

        $html .=
                '<div class="input-group">' .
                '<div class="input-group-prepend">' .
              
                //TODO we'd like to use dynamic PLAC input as 'shared-place-name' here (requires script to read value, but how to update data-href?)
                //(for _LOC under PLAC)
                '<button class="btn btn-secondary" type="button" data-toggle="modal" data-target="#wt-ajax-modal-vesta" data-href="' . e(route(CreateSharedPlaceModal_20::class, ['tree' => $tree->name(), 'selector' => $selector])) . '" data-select-id="' . $id . '" title="' . I18N::translate('Create a shared place') . '">' .
              
                '' . view('icons/add') . '<' .
                '/button>' .
                '</div>' .
                view('components/select-location', [
                    'id' => $id, 
                    'name' => $name, 
                    'location' => $location, 
                    'selectorForDate' => $selectorForDate, 
                    'tree' => $tree]) .
                '</div>';
        
        //for _LOC under PLAC, upperlevel sometimes (existing tag) given as INDI/FAM, inconsistently, probably not worth investigating
        if (Str::endsWith($upperlevel, 'PLAC') || ($upperlevel === 'INDI') || ($upperlevel === 'FAM')) {
          View::push('javascript');
          $script = '<script>' .
              '$(\'#' . $id . '\').on(\'select2:select\', function (e) {' .
              '    var data = e.params.data;' .
              //'    updatewholenamePLAC(\'' . $locationName . '\', data.title, \'' . $selector. '\');' .
              '    updatewholenamePLAC2(' . (($location !== null)?'true':'false') . ', data.title, \'' . $selector. '\');' .
              '});' . 
              '</script>';
          echo $script;
          View::endpush();
        }        
        
        break;
      case 'LATI':
        //same as original FunctionsEdit
        //(but for LATI/LONG under PLAC, note the $previous_ids hack!)
        $html .= '<input class="form-control" type="text" id="' . $id . '" name="' . $name . '" value="' . e($value) . '" oninput="webtrees.reformatLatitude(this, \'N\', \'S\')">';
        break;
      case 'LONG':
        //same as original FunctionsEdit
        //(but for LATI/LONG under PLAC, note the $previous_ids hack!)
        $html .= '<input class="form-control" type="text" id="' . $id . '" name="' . $name . '" value="' . e($value) . '" oninput="webtrees.reformatLongitude(this, \'E\', \'W\')">';
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
      
      //DATE (not under 0 _LOC, but elsewhere)  
      case 'DATE':
        //we just add name2
        //for identication
        
        // Need to know if the user prefers DMY/MDY/YMD so we can validate dates properly.
        $localization_service = app(LocalizationService::class);
        $dmy = '"' . $localization_service->dateFormatToOrder(I18N::dateFormat()) . '"';

        $html .= '<div class="input-group">';
        $html .= '<input class="form-control" type="text" id="' . $id . '" name="' . $name . '" data-vesta-name="' . $name2 . '" value="' . e($value) . '" onchange="webtrees.reformatDate(this, ' . e($dmy) . ')" dir="ltr">';
        $html .= view('edit/input-addon-calendar', ['id' => $id]);
        $html .= view('edit/input-addon-help', ['fact' => 'DATE']);
        $html .= '</div>';
        $html .= '<div id="caldiv' . $id . '" style="position:absolute;visibility:hidden;background-color:white;z-index:1000"></div>';
        $html .= '<p class="text-muted">' . (new Date($value))->display() . '</p>';
        break;
      //PLAC (not under 0 _LOC, but elsewhere with _LOC subtag)  
      case 'PLAC':
        $html .= '<div class="input-group">';
        
        $attributes = [
                'autocomplete'          => 'off',
                'class'                 => 'form-control',
                'id'                    => $id,
                'name'                  => $name,
                'data-vesta-name'       => $name2, //for identication (update via _LOC, $name is unhelpful in case of existing fact)
                'value'                 => $value,
                //'type'                  => 'text',
                'data-autocomplete-url' => route(AutoCompletePlace::class, ['tree'  => $tree->name()]),

                //obsolete
                //'data-vesta-unchanged'  => 'true',
            
                //obsolete
                //'oninput'               => 'updateTextNamePLAC(\'' . $id . '\')',
            
                'oninput'               => 'updateTextNamePLAC2(\'' . $id . '\')',
            ];
        
        if ($value !== '') {
          $attributes['data-vesta-plac-was-set'] = 'true';
        }
        
        $html .= '<input ' . Html::attributes($attributes) . '>';

        //[RC] twitter-typeahead stuff apparently wraps input, leading to styling issues wrt background-color when using 'readonly'
        //solution via readonly input would otherwise be preferable to second input element
        //(this was intended to work similar to toggling of individuals' name editing)
        //
        //TODO use proper custom icon here!
        //$html .= '<div class="input-group-append"><span class="input-group-text"><a href="#edit_name" onclick="convertHiddenPLAC(\'' . $id . '\'); return false" class="icon-edit_indi" title="' . I18N::translate('Toggle direct place name editing') . '"></a></span></div>';
        //
        //seems better not to have to toggle explicitly anyway
        //we still need a second element (or javascript state) to keep track of manual changes
                
        //cf 'NAME' in original FunctionsEdit 
        /*
        // Populated in javascript from sub-tags
        $html .= '<input type="hidden" id="' . $id . '" name="' . $name . '" oninput="updateTextName(\'' . $id . '\')" value="' . e($value) . '" class="' . $fact . '">';
        $html .= '<span id="' . $id . '_display" dir="auto">' . e($value) . '</span>';
        $html .= ' <a href="#edit_name" onclick="convertHidden(\'' . $id . '\'); return false" class="icon-edit_indi" title="' . I18N::translate('Edit the name') . '"></a>';
        */
        
        $html .= view('edit/input-addon-coordinates', ['id' => $id]);
        $html .= view('edit/input-addon-help', ['fact' => 'PLAC']);
        $html .= '</div>';
        break;
      default:
        //#76
        //we just default here (relevant e.g. for SOUR:DATA:TEXT)
        return FunctionsEdit::addSimpleTag($tree, $tag, $upperlevel, $label);
        
        /*
        //#17
        ////may be a custom tag, so  just allow editing
        //throw new Exception("unexpected tag: " . $fact);
        $html .= '<input class="form-control" type="text" id="' . $id . '" name="' . $name . '" value="' . e($value) . '">';
        */
        break;
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
            //TODO
            return $module->govIdEditControl(($value === '')?null:$value, $id, $name, $placeName, null, false, false);
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
