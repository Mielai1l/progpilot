<?php

/*
 * This file is part of ProgPilot, a static analyzer for security
 *
 * @copyright 2017 Eric Therond. All rights reserved
 * @license MIT See LICENSE at the root of the project for more info
 */


namespace progpilot\Transformations\Php;

use PHPCfg\Block;
use PHPCfg\Op;

use progpilot\Objects\MyFunction;
use progpilot\Objects\MyDefinition;
use progpilot\Objects\MyExpr;

use progpilot\Code\MyInstruction;
use progpilot\Code\Opcodes;
use progpilot\Transformations\Php\Transform;

class ArrayExpr {

	public static function instruction($op, $context, $arr, $def_name, $is_returndef)
	{
        $assign_id = rand();
		$building_arr = false;

		if(isset($op->ops[0]->values))
		{
			$nb_arrayexpr = 0;
			foreach($op->ops[0]->values as $value)
			{
				$name = Common::get_name_definition($value);
				$type = Common::get_type_definition($value);

				// we create an element for each value of array expr
				// name_arr = [expr1, expr2] => name_arr[0] = expr1, name_arr[1] = expr2
                if(isset($op->ops[0]->keys[$nb_arrayexpr]->value))
                {
                    $index_value = $op->ops[0]->keys[$nb_arrayexpr]->value;
                    $building_arr = array($index_value => $arr);
                }
                else    
                    $building_arr = array($nb_arrayexpr => $arr);

				if($type == "arrayexpr")
				{
					$building_arr = ArrayExpr::instruction($value, $context, $building_arr, $def_name, $is_returndef);
				}
				else
				{
					$mydef = new MyDefinition($context->get_current_line(), $context->get_current_column(), $def_name, false, true);
                    $mydef->set_assign_id($assign_id);
                    
					if($is_returndef)
						$context->get_current_func()->add_return_def($mydef);

					$context->get_mycode()->add_code(new MyInstruction(Opcodes::START_ASSIGN));
					$context->get_mycode()->add_code(new MyInstruction(Opcodes::START_EXPRESSION));

					$myexpr = new MyExpr($context->get_current_line(), $context->get_current_column());
					$myexpr->set_assign(true);
					$myexpr->set_assign_def($mydef);

					Expr::instruction($value, $context, $myexpr, null, $assign_id);

					$inst_end_expr = new MyInstruction(Opcodes::END_EXPRESSION);
					$inst_end_expr->add_property("expr", $myexpr);
					$context->get_mycode()->add_code($inst_end_expr);

					$context->get_mycode()->add_code(new MyInstruction(Opcodes::END_ASSIGN));

					// we reverse the arr
					$arrtrans = BuildArrays::build_array_from_arr($building_arr, false);
					$mydef->set_arr(true);
					$mydef->set_arr_value($arrtrans);

					$inst_def = new MyInstruction(Opcodes::DEFINITION);
					$inst_def->add_property("def", $mydef);
					$context->get_mycode()->add_code($inst_def);

					unset($myexpr);
					unset($mydef);
				}

				$nb_arrayexpr ++;
			}
		}

		return $building_arr;
	}
}

?>