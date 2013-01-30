<?php
/**
 * CakePOWER, CREDITS and LICENSING
 * =====================================
 *
 * @author: 	Marco Pegoraro (aka MPeg, @ThePeg)
 * @mail: 		marco(dot)pegoraro(at)gmail(dot)com
 * @blog:		http://movableapp.com
 * @web:		http://cakepower.org
 * 
 * This sofware is distributed under MIT license.
 * Please read "license.txt" document into plugin's root
 * 
 */




/**
 * PowerTreeHelper
 * Utility to display threaded data.
 *
 * @author: Marco Pegoraro (MPeg)
 * @credits: http://movableapp.com/
 * @compatibility: 5.2.13, 5.3.2
 *
 * This class handle threaded data like data structures generated by a Model::find('threaded') call:
 *
 * array(
 *     0 => array(
 *         'Model' => array( 'id'=>'value', 'title'=>'value', 'fieldName'=>'value' ),
 *         'children' => array(
 *             0 => array(
 *                 'Model' => array( 'id'=>'value', 'title'=>'value', 'fieldName'=>'value' ),
 *                 'children' => array( ... )
 *             )
 *         )
 *     )
 * )
 * -------------
 *
 *
 * The pourpose of this helper is to generate some alternative data structures (HTML, XML, JSON) from
 * the data array allowing an external manipulation of the data itself.
 *
 */
class PowerTreeHelper extends AppHelper {
	
	public $helpers = array(
		'Html'
	);
	
	/**
	 * Internal configuration.
	 * see "extendConfig()" method for details.
	 */
	private $__config = array();
	
	/**
	 * Store the data-tree.
	 */
	private $__tree = array();
	
	/**
	 * Store the output buffer while generating some kind of output data.
	 */
	private $__output = '';
	
	private $__lines = array();
	
	/**
	 * Contains a custom object (extension of TreeHelperExtension).
	 * callable's methods are used to customize the TreeHelper behavior in some internal actions.
	 *
	 * For details looks at:
	 * [private] __buildItemText()
	 * [class] TreeHelperExtension
	 */
	private $__callable = false;
	
	
	
	
	
	
	
	
	
	
	###############################################################################################
	###   P U B L I C     M E T H O D S                                                         ###
	###############################################################################################
	
	
	public function setTree( $tree ) {
		
		$this->__tree = $tree;
		
	} // EndOf: "setTree()" ###
	
	
	
	
	/**
	 * Merge actual configuration with new values contained in a configuration array.
	 */
	public function extendConfig( $config ) {
		
		// Extend local configuration with external values.
		$this->__config = array_merge(array(
			
			'children'		=> 'children', // Item's children container name.
			
			// Output configuration.
			'output'		=> 'HTML', // [HTML,XML,JSON] - case insensitive.
			'listTag' 		=> 'ul',
			'listOpt'		=> array(),
			'itemTag'		=> 'li',
			'itemOpt'		=> array(),
			
			// Data handling
			// used in "__buildItemText()" to show a node content
			'displayModel'	=> '', 		// Data model to be displayed
			'displayField'	=> '', 		// Data field to be displayed
			'displayLogic'	=> false,	// Callable method to be used to define what to display
			
			'deepLimit' 	=> false,
			
			// HTML Stiling
			// some chars to beautify generated code!
			'codeIndent'	=> true,
			't'				=> '	',
			'ln'			=> "\r\n",
			
		),$config);
		
		
		// Values optimizazion.
		$this->__config['output'] = strtoupper($this->__config['output']);
		
		// Setup callable object.
		// Callable must be instance of TreeHelperExtension class to be allowed inside this helper!
		if ( $this->config('callable') ) {
			
			if ( $this->config('callable') instanceof TreeHelperExtension ) {
				
				$this->config('callable')->setSubject( $this );
				
			} else {
				
				$this->config( 'callable', false );
				
			}
			
		}
		
		return $this->__config;
	
	} // EndOf: "extendConfig()" ##################################################################
	
	
	
	/**
	 * Reset local configuration and sets up new values.
	 */ 
	public function setConfig( $config ) {
		
		// Reset local configuration.
		$this->__resetConfig();
		
		return $this->extendConfig( $config );
		
	} // EndOf: "setConfig()" #####################################################################
	
	
	
	/**
	 * Local configuration getter/setter.
	 * Shortname for "setConfig()" method.
	 */
	public function config( $key, $val = '' ) {
		
		// Extends local configuration with array.
		if ( is_array($key) ) {
			
			return $this->extendConfig($key);
			
		}
		
		// Getter
		if ( func_num_args() == 1 ) {
			
			if ( array_key_exists($key,$this->__config) ) {
				
				return $this->__config[$key];
				
			}
		
		// Setter
		} else {
			
			$this->__config[$key] = $val;
			
			// Return value for confirmation.
			return $this->config( $key );
			
		}
		
		return false;
		
	} // EndOf: "config()" ############################################################################
	
	
	public function generate( $tree, $config = array() ) {
		
		// Simple config for JSON output.
		if ( $config == 'json' ) $config = array( 'output'=>'json' );
		
		// Setup local configuration and work data.
		$this->setConfig($config);
		$this->setTree($tree);
		
		
		$this->__init();
		
		
		switch ( $this->config('output') ) {
			
			case 'HTML':
				return $this->__buildHTML( $this->__tree, 0 );	
				return $this->__outputHTML();
				
			case 'JSON':
				return json_encode($this->__buildJSON( $this->__tree, 0 ));
			
		}
		
		
	} // EndOf: "generate()" ######################################################################
	
	
	
	
	
	
	
	
	
	
	
	###############################################################################################
	###   I N T E R N A L     M E T H O D S                                                     ###
	###############################################################################################
	
	
	public function __resetConfig() {
		
		$this->__config = array();
	
	}
		
	
	/**
	 * Reset output propreties to startup a new output process.
	 */
	private function __init() {
		
		$this->__actualDepth 	= 0;
		
		$this->__output 		= '';
		
		$this->__lines 			= array();
	
	} // EndOf: "__init()" ########################################################################
	
	
	/**
	 * Tree item customization by external code
	 * This method allow an external class and an external callback to alter the item's data structure.
	 *
	 * In HTML mode this method is called before the item's text generation.
	 * In JSON mode thit is the only way to customize item's jsoned data.
	 *
	 * External class method and callback are executed one after one.
	 * Them can return these kind of values:
	 *
	 * [array] altered data: is sent to the next step of customization (object >>> callback) then
	 *                       is sent back to the building method.
	 *
	 * false:                immediately stop the customization and jump the item from the code generation
	 *
	 * null: just ignore the callback then go on!
	 */
	private function __buildItem( $treeItem, $depth ) {
		
		// Use the extension class:
		if ( $this->config('callable') ) {
			
			$tmp = $this->config('callable')->itemLogic( $treeItem, $depth );
			
			if ( $tmp === false ) return $tmp;
			
			if ( is_array($tmp) ) $treeItem = $tmp;
			
		}
		
		
		// Throw the callback function:
		if ( $this->config('itemLogic') ) {
			
			$tmp = call_user_func( $this->config('itemLogic'), $treeItem, $depth, $this );
			
			if ( $tmp === false ) return $tmp;
			
			if ( is_array($tmp) ) $treeItem = $tmp;
			
		}
		
		
		// Send item back to the production logic.
		return $treeItem;
	
	} // EndOf: "__buildItem()" ###################################################################
	
	
	
	
	/**
	 * It build the content of a tree item by calling external resources for extreme customization.
	 *
	 * Used by the HTML mode to generate "what to display" inside the LI node.
	 */
	private function __buildItemText( $tree, $depth ) {
		
		// Use the extension class:
		if ( $this->config('callable') ) return $this->config('callable')->displayLogic( $tree, $depth );
		
		// Throw the callback function:
		if ( $this->config('displayLogic') ) return call_user_func( $this->config('displayLogic'), $tree, $depth, $this );
		
		// Try to build a text from the model/field configuration:
		if ( !empty($tree[$this->config('displayModel')][$this->config('displayField')]) ) return $tree[$this->config('displayModel')][$this->config('displayField')];
		
		// Try to search for a Model/field info throught the data array.
		$tmp = array_keys($tree);
		if ( count($tmp) ) {
			
			$model = $tmp[0];
			
			$tmp = array_keys($tree[$model]);
			
			if ( empty($field) && in_array('title',$tmp) ) 	$field = 'title';
			if ( empty($field) && in_array('name',$tmp) ) 	$field = 'name';
			if ( empty($field) && in_array('id',$tmp) ) 	$field = 'id';
			if ( empty($field) ) 							$field = $tmp[0];
			
			if ( !empty($field) && !is_array($tree[$model][$field]) ) return $tree[$model][$field];
			
		} 
		
		// Extremely default value for the row!
		return "TreeItem";
	
	} // EndOf: "__buildItemText()" ###############################################################
	
	
	/**
	 * Check configuration to decide if another recursion into the data tree is allowed or not.
	 */
	private function __canRecurse( $depth ) {
		
		if ( !$this->config('deepLimit') ) return true;
		
		return ( $depth < $this->config('deepLimit')-1 );
	
	} // EndOf: "__canRecurse()" ##################################################################
	
	
	private function __line( $str ) {
		
		$this->__lines[] = $str;
		
	} // EndOf: "__line()" ########################################################################
	
	
	
	
	
	
	
	
	/**
	 * ---[[    H T M L     G E N E R A T I O N    ]]---
	 */
	
	private function __buildHTML( $tree, $depth ) {
		
		// Setup indentation chars for the item.
		
		$t = $tt = $ttt = '';
		
		if ( $this->config('codeIndent') ) {
		
			for ( $i=0; $i<$depth; $i++ ) $t .= $this->config('t');
			
			$tt = $t . $this->config('t');
		
		}
		
		
		// Build the item's related code.
		
		//if ( $this->config('listTag') ) $this->__line( $t . '<' . $this->config('listTag') . '>' );
		ob_start();
		
		for ( $i=0; $i<count($tree); $i++ ) {
			
			// Customization of the item and the display text for the item itself.
			$tree[$i] = $this->__buildItem( $tree[$i], $depth );
			
			// Customization can set item data to false to jump the item itself:
			if ( $tree[$i] !== false ) {
				
				//if ( $this->config('itemTag') ) $this->__line( $tt . '<' . $this->config('itemTag') . ' class="level-' . $depth . '">' );
				
				$itemText = $this->__buildItemText( $tree[$i], $depth );
			
				//$this->__line( $tt . $itemText );
			
			
				// Recursion inside the tree.
				if ( $this->__canRecurse($depth) && !empty($tree[$i][$this->config('children')]) ) {
				
					//$this->__buildHTML( $tree[$i][$this->config('children')], $depth+1 );
					$itemText.= $this->__buildHTML( $tree[$i][$this->config('children')], $depth+1 );
				
				}
				
				// builds item's tag configuration
				$itemOpt = PowerHtmlHelper::tagOptions($this->config('itemOpt'));
				$itemOpt = $this->__buildHTML_itemOpt( $itemOpt, $tree[$i], $depth );
			
				//if ( $this->config('itemTag') ) $this->__line( $tt . '</' . $this->config('itemTag') . '>' );
				echo $this->Html->tag( $this->config('itemTag'), $itemText, $itemOpt );
			
			}
			
		} 
		
		// bulds list's tag configuration
		$listOpt = PowerHtmlHelper::tagOptions($this->config('listOpt'));
		$itemOpt = $this->__buildHTML_listOpt( $itemOpt, $tree, $depth );
		
		if ( $this->config('listTag') ) return $this->Html->tag( $this->config('listTag'), ob_get_clean(), $listOpt ); else return ob_get_clean();
	
	} // EndOf: "__buildHTML()" ###################################################################
	
	
	/**
	 * Allows external modifications of list item tag options.
	 * 
	 * @param unknown_type $cfg
	 * @param unknown_type $treeItem
	 * @param unknown_type $depth
	 */
	protected function __buildHTML_itemOpt( $cfg, $treeItem, $depth ) {
		
		// Use the extension class:
		if ( $this->config('callable') ) {
			
			$tmp = $this->config('callable')->itemOptions( $cfg, $treeItem, $depth );
			
			if ( $tmp === false ) return $cfg;
			
			if ( $tmp !== null ) $cfg = $tmp;
			
		}
		
		
		// Throw the callback function:
		if ( $this->config('itemOptions') ) {
			
			$tmp = call_user_func( $this->config('itemOptions'), $cfg, $treeItem, $depth, $this );
			
			if ( $tmp === false ) return $cfg;
			
			if ( $tmp !== null ) $cfg = $tmp;
			
		}
		
		return $cfg;
		
	}
	
	/**
	 * Allows external modifications of list item tag options.
	 * 
	 * @param unknown_type $cfg
	 * @param unknown_type $treeItem
	 * @param unknown_type $depth
	 */
	protected function __buildHTML_listOpt( $cfg, $tree, $depth ) {
		
		// Use the extension class:
		if ( $this->config('callable') ) {
			
			$tmp = $this->config('callable')->listOptions( $cfg, $tree, $depth );
			
			if ( $tmp === false ) return $cfg;
			
			if ( $tmp !== null ) $cfg = $tmp;
			
		}
		
		
		// Throw the callback function:
		if ( $this->config('listOptions') ) {
			
			$tmp = call_user_func( $this->config('listOptions'), $cfg, $tree, $depth, $this );
			
			if ( $tmp === false ) return $cfg;
			
			if ( $tmp !== null ) $cfg = $tmp;
			
		}
		
		return $cfg;
		
	}
	
	
	private function __outputHTML() {
		
		foreach ( $this->__lines as $line ) {
			
			$this->__output .= $this->config('ln') . $line;
		
		}
		
		return $this->__output;
	
	} // EndOf: "__outputHTML()" ##################################################################
	
	
	
	
	
	
	
	
	
	
	/**
	 * ---[[     J S O N      G E N E R A T I O N      ]]---
	 */
	
	private function __buildJSON( $tree, $depth ) {
		
		$new_tree = array();
		
		for ( $i=0; $i<count($tree); $i++ ) {
			
			// Scroporate items.
			$item 	= $tree[$i];
			$childs = $item[$this->config('children')];
			
			// Reset items.
			$item[$this->config('children')] = array();
			
			// Customization of item data.
			$item = $this->__buildItem( $item, $depth );
			
			// Customization can set item data to false to jump the item itself:
			if ( $item !== false ) {
			
				// Recursion inside the tree.
				if ( $this->__canRecurse($depth) && !empty($childs) ) {
					
					$item[$this->config('children')] = $this->__buildJSON( $childs, $depth+1 );
					
				}
			
				$new_tree[] = $item;
			
			}
			
		}
		
		return $new_tree;
	
	} // EndOf: "__buildJSON()" ###################################################################
	
	
}




/**
 * Extension class to customize how TreeHelper works with custom data.
 * You need to create your custom extension class filled by your logic.
 *
 * In your view's code you can write something like:
 *
 * --------------
 * class MyTreeExtension extends TreeHelperExtension {
 *
 *    function displayLogic( $tree ) { return $tree['Model']['field']; }
 * 
 * }
 *
 * $this->Tree->generate( $tree_data, array(
 *    'callable'=> new MyTreeExtension  
 * ));
 * --------------
 *
 * Methods in your extension class can access TreeHelper's using "$this->subject()":
 *
 * --------------
 * return $this->subject()->Html->link( $tree['Model']['field'], array( 'action'=>'edit', $tree['Model']['id'] ));
 * --------------
 *
 */
class TreeHelperExtension {

	protected $__subject;
	
	
	public function setSubject( $subject ) {
		
		$this->__subject = $subject;
	
	}
	
	
	protected function subject() {
		
		return $this->__subject;
	
	}
	
	/**
	 * Allows item customization.
	 * return values:
	 */
	public function itemLogic( $treeItem, $depth ) {}
	
	public function displayLogic( $tree, $depth ) {}
	
	
	/**
	 * Allows tag options modifications
	 * 
	 * @param unknown_type $cfg
	 * @param unknown_type $treeItem
	 * @param unknown_type $depth
	 */
	
	public function itemOptions( $cfg, $treeItem, $depth ) {}
	
	public function listOptions( $cfg, $tree, $depth ) {}

}

