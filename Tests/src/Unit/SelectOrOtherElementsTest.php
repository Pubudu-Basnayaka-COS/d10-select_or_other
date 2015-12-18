<?php

namespace Drupal\Tests\select_or_other\Unit;

use Drupal\Core\Form\FormState;
use Drupal\select_or_other\Element\SelectOrOtherButtons;
use Drupal\select_or_other\Element\SelectOrOtherElementBase;
use Drupal\select_or_other\Element\SelectOrOtherSelect;
use Drupal\Tests\UnitTestCase;
use ReflectionMethod;

/**
 * Tests the form element implementation.
 *
 * @group select_or_other
 * @covers \Drupal\select_or_other\Element\SelectOrOtherElementBase
 * @covers \Drupal\select_or_other\Element\SelectOrOtherButtons
 * @covers \Drupal\select_or_other\Element\SelectOrOtherSelect
 */
class SelectOrOtherElementsTest extends UnitTestCase {

  /**
   * Tests the addition of the other option to an options array.
   */
  public function testAddOtherOption() {
    $options = [];

    // Make the protected method accessible and invoke it.
    $method = new ReflectionMethod('Drupal\select_or_other\Element\SelectOrOtherElementBase', 'addOtherOption');
    $method->setAccessible(TRUE);
    $options = $method->invoke(NULL, $options);

    $this->assertArrayEquals(['select_or_other' => "Other"], $options);
  }

  /**
   * Tests the value callback.
   */
  public function testValueCallback() {
    $form_state = new FormState();
    $element = [
      '#multiple' => FALSE,
    ];
    $input = [
      'select' => 'Selected text',
      'other' => 'Other text',
    ];

    $expected = [$input['select']];
    $values = SelectOrOtherElementBase::valueCallback($element, $input, $form_state);
    $this->assertArrayEquals($expected, $values, 'Returned single value select.');

    $input['select'] = 'select_or_other';
    $expected = [$input['other']];
    $values = SelectOrOtherElementBase::valueCallback($element, $input, $form_state);
    $this->assertArrayEquals($expected, $values, 'Returned single value other.');

    $element['#multiple'] = TRUE;
    $input['select'] = ['Selected text'];
    $expected = ['select' => $input['select'], 'other' => []];
    $values = SelectOrOtherElementBase::valueCallback($element, $input, $form_state);
    $this->assertArrayEquals($expected, $values, 'Returned select array and empty other array.');

    $input['select'][] = 'select_or_other';
    $expected['other'] = [$input['other']];
    $values = SelectOrOtherElementBase::valueCallback($element, $input, $form_state);
    $this->assertArrayEquals($expected, $values, 'Returned select array and other array.');

    $input['select'] = ['select_or_other'];
    $expected = ['select' => [], 'other' => [$input['other']]];
    $values = SelectOrOtherElementBase::valueCallback($element, $input, $form_state);
    $this->assertArrayEquals($expected, $values, 'Returned empty select and other array.');

    $input['select'][] = 'Selected';
    $element['#merged_values'] = TRUE;
    $expected = ['Selected', $input['other']];
    $values = SelectOrOtherElementBase::valueCallback($element, $input, $form_state);
    $this->assertArrayEquals($expected, $values, 'Returned merged array.');

    $input['select'] = ['Selected'];
    $input['other'] = '';
    $expected = ['Selected'];
    $values = SelectOrOtherElementBase::valueCallback($element, $input, $form_state);
    $this->assertArrayEquals($expected, $values, 'Returned merged array.');

    foreach ([TRUE, FALSE] as $multiple) {
      $element['#multiple'] = $multiple;
      $input = ['other' => 'Other value'];
      $expected = [];
      $values = SelectOrOtherElementBase::valueCallback($element, $input, $form_state);
      $this->assertArrayEquals($expected, $values, 'Submitting only the other value results in an empty array.');
    }

  }

  /**
   * Tests the processing of a select or other element.
   */
  public function testProcessSelectOrOther() {
    // Test SelectOrOtherElementBase.
    // Make the protected method accessible and invoke it.
    $method = new ReflectionMethod('Drupal\select_or_other\Element\SelectOrOtherElementBase', 'addOtherOption');
    $method->setAccessible(TRUE);

    $form_state = new FormState();
    $form = [];
    $original_element = $element = [
      '#name' => 'select_or_other',
      '#default_value' => 'default',
      '#required' => TRUE,
      '#multiple' => FALSE,
      '#options' => [
        'first_option' => 'First option',
        'second_option' => "Second option"
      ],
    ];

    $base_expected_element = $expected_element = $element + [
        'select' => [
          '#default_value' => $element['#default_value'],
          '#required' => $element['#required'],
          '#multiple' => $element['#multiple'],
          '#options' => $method->invoke(NULL, $element['#options']),
          '#weight' => 10,
        ],
        'other' => [
          '#type' => 'textfield',
          '#weight' => 20,
        ]
      ];

    $resulting_element = SelectOrOtherElementBase::processSelectOrOther($element, $form_state, $form);
    $this->assertArrayEquals($expected_element, $resulting_element);
    $this->assertArrayEquals($resulting_element, $element);

    // Test single cardinality SelectOrOtherButtons.
    $element = $original_element;
    $expected_element = array_merge_recursive($base_expected_element, [
      'select' => [
        '#type' => 'checkboxes',
      ],
      'other' => [
        '#states' => [
          'visible' => [
            ':input[name="' . $element['#name'] . '[select][select_or_other]"]' => ['checked' => TRUE],
          ],
        ],
      ],
    ]);
    $element['#multiple'] = $expected_element['#multiple'] = $expected_element['select']['#multiple'] = TRUE;
    $resulting_element = SelectOrOtherButtons::processSelectOrOther($element, $form_state, $form);
    $this->assertArrayEquals($expected_element, $resulting_element);
    $this->assertArrayEquals($resulting_element, $element);

    // Test multiple cardinality SelectOrOtherButtons.
    $element = $original_element;
    $expected_element = array_merge_recursive($base_expected_element, [
      'select' => ['#type' => 'radios'],
      'other' => [
        '#states' => [
          'visible' => [
            ':input[name="' . $element['#name'] . '[select]"]' => ['value' => 'select_or_other'],
          ],
        ],
      ],
    ]);
    $resulting_element = SelectOrOtherButtons::processSelectOrOther($element, $form_state, $form);
    $this->assertArrayEquals($expected_element, $resulting_element);
    $this->assertArrayEquals($resulting_element, $element);

    // Test single cardinality SelectOrOtherSelect
    $element = $original_element;
    $expected_element = array_merge_recursive($base_expected_element, [
      'select' => ['#type' => 'select'],
      'other' => [
        '#states' => [
          'visible' => [
            ':input[name="' . $element['#name'] . '[select]"]' => ['value' => 'select_or_other'],
          ],
        ],
      ],
    ]);
    $resulting_element = SelectOrOtherSelect::processSelectOrOther($element, $form_state, $form);
    $this->assertArrayEquals($expected_element, $resulting_element);
    $this->assertArrayEquals($resulting_element, $element);

    // Test single cardinality SelectOrOtherSelect
    $element = $original_element;
    $expected_element = array_merge_recursive($base_expected_element, [
      'select' => [
        '#type' => 'select',
        '#multiple' => TRUE,
        '#attached' => [
          'library' => ['select_or_other/multiple_select_states_hack']
        ],
      ],
    ]);
    $element['#multiple'] = $expected_element['#multiple'] = $expected_element['select']['#multiple'] = TRUE;
    $resulting_element = SelectOrOtherSelect::processSelectOrOther($element, $form_state, $form);
    $this->assertArrayEquals($expected_element, $resulting_element);
    $this->assertArrayEquals($resulting_element, $element);

  }

}
