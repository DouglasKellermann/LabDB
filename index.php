<?php 
session_start();
error_reporting( E_ERROR | E_PARSE );

if ($_REQUEST['operacao'] != "autocomplete"){?>
<html>
<head>
<title>Disciplina de Banco de Dados</title>
<meta charset="UTF-8">
<link rel="stylesheet" href="autocomplete/jquery-ui.css">
<script src="autocomplete/jquery-1.10.2.js"></script>
<script src="autocomplete/jquery-ui.js"></script>
<link rel="stylesheet" href="autocomplete/style.css">
<style>
  .ui-autocomplete-loading {
    background: white url("autocomplete/ui-anim_basic_16x16.gif") right center no-repeat;
  }
</style>
<script>

	var fks = {};
    function log( id_log, message ) {
      $( "#" + id_log ).text( " " + message ); //.prependTo( "#" + id_log );
      //$( "#log" ).scrollTop( 0 );
    }

	function define_autocomplete( obj ) {
		var label_fk = "label_" + $(obj).attr("id");
		var fk = fks[$(obj).attr("id")];
		$( obj ).autocomplete({
		  source: function( request, response ) {
			$.ajax({
			  url: "index.php",
			  dataType: "jsonp",
			  data: {
				pesquisa: request.term,
				operacao: "autocomplete",
				fk: fk
			  },
			  success: function( data ) {
				response( data );
			  }
			});
		  },
		  minLength: 3,
		  select: function( event, ui ) {
			log( label_fk, ui.item ?
			  " " + ui.item.label + " (" + ui.item.value + ")" :
			  "Não selecionado, parâmetro foi " + this.value);
				// prevent autocomplete from updating the textbox
				event.preventDefault();
				// manually update the textbox and hidden field
				$(this).val(ui.item.value);
				$( "#" + label_fk ).val(ui.item.value);			  
		  },
		  response: function( event, ui ) {
			log( label_fk, ui.item ?
			  " " + ui.item.label + " (" + ui.item.value + ")" :
			  "");
		  },
		  open: function() {
			$( this ).removeClass( "ui-corner-all" ).addClass( "ui-corner-top" );
		  },
		  close: function() {
			$( this ).removeClass( "ui-corner-top" ).addClass( "ui-corner-all" );
		  }
		});
		
	}
	
</script>
</head>
<?php }

$conexao = null;
$ini_array = parse_ini_file("bancodedados.ini", true);
//die(print_r($ini_array));

function opcoes_de_reset() {
    $html = "";
    $html .= "<div style=\"float: right;\">\n";
    if (isset($_SESSION['base_de_dados'])) {
        $html .= "<a href=\"?operacao=reset_base_de_dados\"><strong>base de dados</strong></a>";
        $html .= " &gt ";
		$html .= "<a href=\"?operacao=reset_tabela\">" . $_SESSION['base_de_dados'] . "</a>";
    }
    $html .= "</div>\n";
    echo $html;
}

function conecta_banco() {
    global $conexao;
  
    $conexao = mysql_connect('localhost:3306', '', '');
    if (!$conexao) {
        die('Não foi possível conectar como servidor: ' . mysql_error());
    }
    mysql_set_charset('utf8',$conexao);
}

function seleciona_db( $db ) {
    global $conexao;
	mysql_select_db($db, $conexao) or die('Não foi possível selecionar banco de dados');
}

function executa_sql( $instrucao_sql ) {

    $result_set = mysql_query($instrucao_sql);
    
    if (!$result_set) {
        $menssagem  = 'Instrução SQL inválida : ' . mysql_error() . "<BR />\n";
        $menssagem .= 'Instrução completa: ' . $instrucao_sql . "<BR />";
        die($menssagem);
    }
    return $result_set;
}

function autocomplete($base_de_dados, $tabela, $operacao, $chave_estrangeira, $callback, $pesquisa ) {
    global $conexao;
	global $ini_array;
	
	$html = "";
	if (isset($ini_array[$chave_estrangeira])) {
		if (isset($ini_array[$chave_estrangeira]["sql_lista_referenciada"])) {
			$jsonData = array();
			$sql = $ini_array[$chave_estrangeira]["sql_lista_referenciada"];
			$sql = str_replace("%pesquisa%", "%$pesquisa%", $sql);
			//echo $sql;
			$result = executa_sql( $sql );
			while ($array = mysql_fetch_assoc($result)) {
				$jsonData[] = "{ value: \"" . $array["value"] . "\", label: \"" . $array["option"]  . "\" }";
				
			}
			$html = $callback . "([" . implode(", ", $jsonData) . "]);";
		}
	}
    return $html;
}


function monta_select( $base_de_dados, $tabela, $result_set, $titulo ) {
	global $ini_array;
	
    $sql = "";
	$base = "";
	$controle_fk = array();
	
	// faz contagem de fks e campos de joins
	$result_set_fk = record_set_estrutura_fks( $base_de_dados, $tabela );
	$constraint_name = "";
	while ($registro_fk = mysql_fetch_assoc($result_set_fk)) {
		if ($registro_fk["constraint_name"] <> $constraint_name && !isset($controle_fk[$registro_fk["constraint_name"]])) {
			$controle_fk[$registro_fk["constraint_name"]] = array(0, 0);
		}
		$controle_fk[$registro_fk["constraint_name"]][0] += 1;
		$constraint_name = $registro_fk["constraint_name"];
	}
	
    if (mysql_num_rows($result_set) > 0) {
        while ($registro = mysql_fetch_assoc($result_set)) {
            $sql .= ( $sql == "" ? "" : ", " ) . "sel_base.`" . $registro["coluna"] . "`\n";
			$base = "`" . $registro["TABLE_SCHEMA"] . "`.`" . $registro["TABLE_NAME"] . "` as sel_base";
			$schema_table_fk = "`" . $registro["TABLE_SCHEMA"] . "`.`" . $registro["TABLE_NAME"] . "`.`" . $registro["constraint_name"] . "`";
			if (isset($ini_array[$schema_table_fk])) {
				if (isset($ini_array[$schema_table_fk]["descricao_referenciada"])) {
					$sql .= ( $sql == "" ? "" : ", " ) . $registro["constraint_name"] . ".`" . $ini_array[$schema_table_fk]["descricao_referenciada"] . "` as "  .  $registro["referenced_table_name"] . "_" . $ini_array[$schema_table_fk]["descricao_referenciada"] . "\n";
				}
			}
        }
        $sql = "select \n" . $sql;
        $sql .= "from \n";
		$sql .= $base . " \n";
    }
    $result_set_fk = record_set_estrutura_fks( $base_de_dados, $tabela );
	$constraint_name = "";
	$fk = array(); 
    if (mysql_num_rows($result_set_fk) > 0) {
		$base = "";
        while ($registro = mysql_fetch_assoc($result_set_fk)) {
			if ($registro["constraint_name"] <> $constraint_name) {
				if ($constraint_name != "") {
					$fk[$constraint_name] = "left join " . $base . "\non\n" . $fk[$constraint_name];
				}
				$fk[$registro["constraint_name"]] = "";
			}
            $fk[$registro["constraint_name"]] .= ( $fk[$registro["constraint_name"]] == "" ? "" : " and " ) . "sel_base.`" . $registro["column_name"] . "` = " . $registro["constraint_name"] . ".`" . $registro["referenced_column_name"] . "` \n";

			$constraint_name = $registro["constraint_name"];
			$base = "`" . $registro["referenced_table_schema"] . "`.`" . $registro["referenced_table_name"] . "` as " . $registro["constraint_name"];
        }
		if ($constraint_name != "") {
			$fk[$constraint_name] = "left join " . $base . "\non\n" . $fk[$constraint_name];
			foreach ($fk as $key => $join) {
				$sql .= $join . " \n";
				//echo $join . "<BR/>";
			}
		}
    }
    return $sql;
}

function sql_para_tablehtml( $sql, $titulo ) {
    global $conexao;
    $result_set = executa_sql( $sql );
    return resultset_para_tablehtml($result_set, $titulo);

}

function resultset_para_tablehtml( $result_set, $titulo ) {
    $html = "";
    if (mysql_num_rows($result_set) > 0) {
        $colunas = mysql_num_fields($result_set);
        $html .= "<table>\n";
        $html .= "<thead>\n";
        $html .= "<th colspan=\"" . mysql_num_fields($result_set) . "\">\n";
        $html .= mb_strtoupper($titulo, 'UTF-8') . "</th>\n";
        $html .= "</thead>\n";
        $html .= "<tr>\n";
        for ($coluna = 0; $coluna < $colunas; $coluna++)
        {
            $html .= "<th>" . mysql_field_name($result_set, $coluna) . "</th>\n";
        }
        $html .= "</tr>\n";
        while ($registro = mysql_fetch_assoc($result_set)) {
            $html .= "<tr>";
			foreach ($registro as $key => $value) {
				$html .= "<td";
				if (preg_match_all("/text-align: \w+/", $value) ) {
				$html .= " style=\"text-align: center;\" ";
				}
				$html .= ">";
				$html .= $value;
				$html .= "</td>";
			}			
			$html .= "</tr>\n";
        }
        $html .= "</table>\n";
    }
    
    return $html;
}

function processa_consulta( $consulta_selecionada ) {
	global $ini_array;
	$html = "";
	//print_r($ini_array);
	//$html = $ini_array['consultas']["consulta$consulta_selecionada"] . " => " . $ini_array['consultas']["sql$consulta_selecionada"] . "<BR/>\n";
	$html .= sql_para_tablehtml( $ini_array['consultas']["sql$consulta_selecionada"], $ini_array['consultas']["consulta$consulta_selecionada"] );
	return $html;
}


function lista_consultas() {
	global $ini_array;
	$consultas = array();
	
    $html = "<BR/>\n<BR/>\n<HR/>\n";
    $html .= "<H4>CONSULTAS REGISTRADAS<H4/>\n";
	
	foreach ($ini_array['consultas'] as $chave => $valor) {
		//$html .= "[ $chave ] => [ $valor ]<BR/>\n";
		$numero = preg_replace('/[^0-9]/', '', $chave);
		$parte = preg_replace('/[0-9]/', '', $chave);
		$espaco = preg_replace('/[^ ]/', '', $chave);
		
		if ($numero != "" && $parte != "" && $espaco == "") {
			if (isset($consultas[$numero])) {
				$consultas[$numero][1] = strtolower($parte);
			} else {
				$consultas[$numero] = array(strtolower($parte), "", false);
			}
		} else {
			echo "ERRO: Parametrização de consulta apresenta chaves sem o formato XXXX999<BR/>\n";
		}
	}
	
	foreach ($consultas as $seq => $consulta_sql) {
		if ( ($consulta_sql[0] == "sql" && $consulta_sql[1] == "consulta") || ($consulta_sql[1] == "sql" && $consulta_sql[0] == "consulta")) {
			$consultas[$seq][2] = true;
			$html .= "<a href=\"?operacao=consulta$seq\">" . $ini_array['consultas']["consulta".$seq] . "</a><BR/>\n";
		}
	}

	return $html;
}

function formulario( $base_de_dados, $tabela, $result_set_estrutura, $titulo, $operacao ) {
	global $ini_array;

    $html = "";
	$campo = null;
    $campo_html = "";
	$campo_script = "";
	$script = array();
	$controle_fk = array();
	
	if ($operacao == "inserir") {
		$operacao = "insert";
	}
	if ($operacao == "alterar") {
		$operacao = "update";
	}

	// faz contagem de fks e campos de joins
	$result_set_fk = record_set_estrutura_fks( $base_de_dados, $tabela );
	$constraint_name = "";
	while ($registro_fk = mysql_fetch_assoc($result_set_fk)) {
		if ($registro_fk["constraint_name"] <> $constraint_name && !isset($controle_fk[$registro_fk["constraint_name"]])) {
			$controle_fk[$registro_fk["constraint_name"]] = array(0, 0);
		}
		$controle_fk[$registro_fk["constraint_name"]][0] += 1;
		$constraint_name = $registro_fk["constraint_name"];
	}
	//print_r($controle_fk);

    if (mysql_num_rows($result_set_estrutura) > 0) {
        $colunas = mysql_num_fields($result_set_estrutura);
        $html .= "<form method=\"GET\">\n";
        $html .= "<input type=\"hidden\" name=\"operacao\" value=\"$operacao\">\n";
        $html .= "<table>\n";
        $html .= "<thead>\n";
        $html .= "<th colspan=\"2\">\n";
        $html .= strtoupper($titulo) . "</th>\n";
        $html .= "</thead>\n";
        while ($registro = mysql_fetch_assoc($result_set_estrutura)) {
			$campo = campo_formulario($base_de_dados, $tabela, $registro, $result_set_fk, $controle_fk);
			$campo_html = $campo[0];
			$campo_script = $campo[1];
			array_push($script, $campo_script);
            $html .= "<tr>
				<th style=\"text-align: right;\" " . ( $registro["comentario"] != "" || $registro["permite nulo"] == "NO"  ? " title=\"" . $registro["comentario"] . ( $registro["permite nulo"] == "NO" ? " (obrigatório)" : "" ) . "\"" : "") . ">" 
				. ( $registro["comentario"] != "" ? $registro["comentario"] : $registro["coluna"] )
				. (	$registro["permite nulo"] == "NO" ? "*" : "") 
				. " " . $registro["*"]
				. "</th>
				<td>" . $campo_html . "</td>
				</tr>\n";
        }
        $html .= "<tr><td></td><td><input type=\"submit\"></td></tr>\n";
        $html .= "</table>\n";
        $html .= "</form>\n";
    }
	
	$html .= implode("\n", $script);
    
    return $html;
}

function campo_formulario( $base_de_dados, $tabela, $result_set_estrutura, $result_set_fk, $controle_fk ) {
	global $ini_array;

    $html = "";
	$input = true;
	$select = false;
	$textarea = false;
	
	$constraint_name = "";
	$base = "";

	$tipo_type = array (
		"date" => "date"
		,"datetime" => "datetime-local"
		,"time" => "time"
	);
	list ($tipo, $tamanho, $subtipo) = split ('[\(\)]', $result_set_estrutura["tipo"]);

	//print_r($result_set_estrutura);
	// verifica chave estrangeira
	//if (preg_match_all("/FK/", $result_set_estrutura["*"])) {
	if ($result_set_estrutura["chave_estrangeira"] == 1) {
		// volta para o inicio do record set
		mysql_data_seek ( $result_set_fk , 0 );
		/*
		constraint_name
		,table_schema
		,table_name
		,column_name
		,referenced_table_schema
		,referenced_table_name
		,referenced_column_name

			,col.COLUMN_NAME coluna
			,col.COLUMN_TYPE tipo
			,col.IS_NULLABLE `permite nulo`
			,col.COLUMN_KEY `características`
			,col.EXTRA extra
			,col.COLUMN_COMMENT comentario 
			,case when pk.constraint_name is not null then 1 else 0 end chave_primaria
			,case when fk.constraint_name is not null then 1 else 0 end chave_estrangeira
			,col.TABLE_SCHEMA
			,col.TABLE_NAME
		*/
		$sql_lista_referenciada = "";
		$rs_lista_referenciada = null;
		while ($registro_fk = mysql_fetch_assoc($result_set_fk)) {
			if ($registro_fk["table_schema"] .".". $registro_fk["table_name"] .".". $registro_fk["column_name"] == $result_set_estrutura["TABLE_SCHEMA"] .".". $result_set_estrutura["TABLE_NAME"] .".". $result_set_estrutura["coluna"]) {
				$constraint_name = $registro_fk["constraint_name"];
				$base = "`" . $registro_fk["table_schema"] . "`.`" . $registro_fk["table_name"] . "`.`" . $registro_fk["constraint_name"] . "`";
				if (isset($ini_array[$base])) {
					if (isset($ini_array[$base]["sql_lista_referenciada"])) {
						// se vier "%pesquisa%" injeta or 1 = 1 para ativar fk autocompete no campo
						$sql_lista_referenciada = str_replace("'%pesquisa%'", "'%pesquisa%' or 1 = 1", $ini_array[$base]["sql_lista_referenciada"]);
						$rs_lista_referenciada = executa_sql ($sql_lista_referenciada);
						if ($registro_lista_referenciada = mysql_fetch_assoc($rs_lista_referenciada)) {
							if (!isset($registro_lista_referenciada["tipo_consulta_fk"])) {
								$sql_lista_referenciada = "";
								$rs_lista_referenciada = null;
							} else {
								if ($registro_lista_referenciada["tipo_consulta_fk"] == "select") {
									$input = false;
									$select = true;
								}
							}
						}
					}
				}
			}
		}

	}
	
	$once = true;
	$autocomplete = false;
	$script_autocomplete = "";
	mysql_data_seek ( $rs_lista_referenciada, 0 );
	while ( (($registro_lista_referenciada = mysql_fetch_assoc($rs_lista_referenciada)) || $once ) && !$autocomplete ) {
	
    if ($input) {
		// abre
		if (isset($registro_lista_referenciada["tipo_consulta_fk"]) && ($registro_lista_referenciada["tipo_consulta_fk"] == "autocomplete") && !$autocomplete) {
			$autocomplete = true;
			$script_autocomplete = "\n<script>\n\tfks['" . $result_set_estrutura["coluna"] . "'] = \"$base\";\n</script>\n";
		}
		
		$html .= "<input ";
		
		// type
		if (isset($registro_lista_referenciada["tipo_consulta_fk"]) && ($registro_lista_referenciada["tipo_consulta_fk"] != "autocomplete")) {
			$html .= "type=\"";
			$html .= $registro_lista_referenciada["tipo_consulta_fk"];
			$html .= "\" ";
			
			$html .= "value=\"";
			$html .= $registro_lista_referenciada["value"];
			$html .= "\" ";

			$html .= "name=\"";
			$html .= $result_set_estrutura["coluna"];
			$html .= "\" ";
			
			$html .= "id=\"";
			$html .= $result_set_estrutura["coluna"]."_".$registro_lista_referenciada["value"];
			$html .= "\" ";
			
		} else {
			$html .= "type=\"";
			$html .= $tipo_type[$tipo];
			$html .= "\" ";

			$html .= "name=\"";
			$html .= $result_set_estrutura["coluna"];
			$html .= "\" ";

			if ($autocomplete) {
			
				$html .= "placeholder=\"pesquisa...\" ";
				$html .= "class=\"fk_autocomplete\" ";
			}
			
			$html .= "id=\"";
			$html .= $result_set_estrutura["coluna"];
			$html .= "\" ";
		}
		
		// required
		if ($result_set_estrutura["permite nulo"] == "NO") {
			// apenas uma vez
			if ($once) {
				$html .= "required ";
			}
		}
		
		// readonly
		if ($result_set_estrutura["extra"] == "auto_increment") {
			$html .= "readonly=\"readonly\" ";
		}

		
		//size
		if (ctype_digit($tamanho)) {
			$html .= "size=\"";
			$html .= $tamanho;
			$html .= "\" ";
			$html .= "maxlength=\"";
			$html .= $tamanho;
			$html .= "\" ";
		}
		
		// fecha
		$html .= ">";
		if (isset($registro_lista_referenciada["tipo_consulta_fk"])) {
			if ($registro_lista_referenciada["tipo_consulta_fk"] == "radio" || $registro_lista_referenciada["tipo_consulta_fk"] == "checkbox") {
				$html .= $registro_lista_referenciada["option"] . "<BR/>";
			}
		} 
		if ($autocomplete) {
			$html .= "<span id=\"label_";
			$html .= $result_set_estrutura["coluna"];
			$html .= "\"></span>\n ";
		}
		
		$html .= "\n";
		
	}
	$once = false;
	
	}
    
    if ($select) {
		// abre ini
		$html .= "<select ";
		// nome
		$html .= "name=\"";
		$html .= $result_set_estrutura["coluna"];
		$html .= "\" ";
		// required
		if ($result_set_estrutura["permite nulo"] == "NO") {
			$html .= "required ";
		}
		// fecha ini
		$html .= ">\n";
		
		$html .= "<option value=\"\"> - selecione - </option>\n";
		mysql_data_seek ( $rs_lista_referenciada, 0 );
		$optgroup = "";
		while ($registro_lista_referenciada = mysql_fetch_assoc($rs_lista_referenciada)) {
			if (isset($registro_lista_referenciada["optgroup"])) {
				if ($registro_lista_referenciada["optgroup"] != $optgroup && $registro_lista_referenciada["optgroup"] != "") {
					if ($optgroup != "") {
						$html .= "</optgroup>\n";
					}
					$html .= "<optgroup label=\"";
					$html .= $registro_lista_referenciada["optgroup"];
					$html .= "\">\n";
				
				}
			}
			$html .= "<option value=\"";
			$html .= $registro_lista_referenciada["value"];
			$html .= "\">";
			$html .= $registro_lista_referenciada["option"];
			$html .= "</option>\n";
			if (isset($registro_lista_referenciada["optgroup"])) {
				$optgroup = $registro_lista_referenciada["optgroup"];
			}
		}
		if ($optgroup != "") {
			$html .= "</optgroup>\n";
		}
		$html .= "</select>\n";
		
	}

    return array($html, $script_autocomplete);
}

function seleciona_base_de_dados() {
    global $conexao;
    $sql = "select distinct SCHEMA_NAME `base de dados`, concat('<a href=\"?base_de_dados=', SCHEMA_NAME, '\">', SCHEMA_NAME, '</a>') as escolha  from information_schema.schemata";
    //echo $sql;
    $result_set = executa_sql( $sql );
    
    return resultset_para_tablehtml($result_set, "base de dados");
    
}

function seleciona_tabela( $base_de_dados ) {
    global $conexao;
    $sql = "select 
				distinct 
				TABLE_NAME `tabela`
				,concat('<a style=\"text-align: center;\" href=\"?tabela=', TABLE_NAME, '\">ver</a>') as estrutura
				,concat('<a style=\"text-align: center;\" href=\"?tabela=', TABLE_NAME, '&operacao=inserir\">inserir</a>') as novo 
				,concat('<a style=\"text-align: center;\" href=\"?tabela=', TABLE_NAME, '&operacao=listar\">listar</a>') as todos
			from 
				information_schema.tables 
			where 
				TABLE_SCHEMA = '$base_de_dados'
			";
    //echo $sql;
    $result_set = executa_sql( $sql );
    return resultset_para_tablehtml($result_set, $base_de_dados);
}

function apresenta_formulario( $base_de_dados, $tabela, $operacao ) {
    global $conexao;
    $sql = "select 
			distinct 
			col.ORDINAL_POSITION seq
			,case 
				when pk.constraint_name is not null then '<span style=\"color: red;\">PK</span>' 
				when fk.constraint_name is not null then '<span style=\"color: gray;\">FK</span>' 
				else '' end as `*`
			,col.COLUMN_NAME coluna
			,col.COLUMN_TYPE tipo
			,col.IS_NULLABLE `permite nulo`
			,col.COLUMN_KEY `características`
			,col.EXTRA extra
			,col.COLUMN_COMMENT comentario 
			,case when pk.constraint_name is not null then 1 else 0 end chave_primaria
			,case when fk.constraint_name is not null then 1 else 0 end chave_estrangeira
			,col.TABLE_SCHEMA
			,col.TABLE_NAME
		from 
			information_schema.columns col
			left join 
			information_schema.key_column_usage pk
			on
				col.TABLE_SCHEMA = pk.TABLE_SCHEMA
				and col.TABLE_NAME = pk.TABLE_NAME
				and col.COLUMN_NAME = pk.COLUMN_NAME
				and pk.constraint_name = 'primary'
			left join 
			information_schema.key_column_usage fk
			on
				col.TABLE_SCHEMA = fk.TABLE_SCHEMA
				and col.TABLE_NAME = fk.TABLE_NAME
				and col.COLUMN_NAME = fk.COLUMN_NAME
				and fk.constraint_name <> 'primary'
				and fk.referenced_table_name is not null
		where 
			col.TABLE_SCHEMA = '$base_de_dados' 
			and col.TABLE_NAME = '$tabela'
		order by 
			col.ORDINAL_POSITION
		";
    //echo $sql;
    $result_set = executa_sql( $sql );
    return formulario($base_de_dados, $tabela, $result_set, /*titulo*/$tabela, $operacao);
}


function lista_tabela( $base_de_dados, $tabela ) {
    global $conexao;
    $sql = "select 
			distinct 
			col.ORDINAL_POSITION seq
			,case 
				when pk.constraint_name is not null then '<span style=\"color: red;\">PK</span>' 
				when fk.constraint_name is not null then '<span style=\"color: gray;\">FK</span>' 
				else '' end as `*`
			,col.COLUMN_NAME coluna
			,col.COLUMN_TYPE tipo
			,col.IS_NULLABLE `permite nulo`
			,col.COLUMN_KEY `características`
			,col.EXTRA extra
			,col.COLUMN_COMMENT comentario 
			,case when pk.constraint_name is not null then 1 else 0 end chave_primaria
			,case when fk.constraint_name is not null then 1 else 0 end chave_estrangeira
			,col.TABLE_SCHEMA
			,col.TABLE_NAME
			,fk.referenced_table_name
			,fk.constraint_name
		from 
			information_schema.columns col
			left join 
			information_schema.key_column_usage pk
			on
				col.TABLE_SCHEMA = pk.TABLE_SCHEMA
				and col.TABLE_NAME = pk.TABLE_NAME
				and col.COLUMN_NAME = pk.COLUMN_NAME
				and pk.constraint_name = 'primary'
			left join 
			information_schema.key_column_usage fk
			on
				col.TABLE_SCHEMA = fk.TABLE_SCHEMA
				and col.TABLE_NAME = fk.TABLE_NAME
				and col.COLUMN_NAME = fk.COLUMN_NAME
				and fk.constraint_name <> 'primary'
				and fk.referenced_table_name is not null
		where 
			col.TABLE_SCHEMA = '$base_de_dados' 
			and col.TABLE_NAME = '$tabela'
		order by 
			col.ORDINAL_POSITION
		";
    //echo $sql;
    $result_set = executa_sql( $sql );
	//echo resultset_para_tablehtml($result_set, $tabela);
	
    $str_select = monta_select($base_de_dados, $tabela,$result_set, $tabela);
	
	//echo $str_select;
	
	$result_set = executa_sql( $str_select );
	
	return resultset_para_tablehtml($result_set, $tabela);
	
}


function apresenta_estrutura( $base_de_dados, $tabela ) {
    global $conexao;
    $sql = "select 
			distinct 
			col.ORDINAL_POSITION seq
			,case 
				when pk.constraint_name is not null then '<span style=\"color: red;\">PK</span>' 
				when fk.constraint_name is not null then '<span style=\"color: gray;\">FK</span>' 
				else '' end as `*`
			,col.COLUMN_NAME coluna
			,col.COLUMN_TYPE tipo
			,col.IS_NULLABLE `permite nulo`
			,col.COLUMN_KEY `características`
			,col.EXTRA extra
			,col.COLUMN_COMMENT comentario 
		from 
			information_schema.columns col
			left join 
			information_schema.key_column_usage pk
			on
				col.TABLE_SCHEMA = pk.TABLE_SCHEMA
				and col.TABLE_NAME = pk.TABLE_NAME
				and col.COLUMN_NAME = pk.COLUMN_NAME
				and pk.constraint_name = 'primary'
			left join 
			information_schema.key_column_usage fk
			on
				col.TABLE_SCHEMA = fk.TABLE_SCHEMA
				and col.TABLE_NAME = fk.TABLE_NAME
				and col.COLUMN_NAME = fk.COLUMN_NAME
				and fk.constraint_name <> 'primary'
				and fk.referenced_table_name is not null
		where 
			col.TABLE_SCHEMA = '$base_de_dados' 
			and col.TABLE_NAME = '$tabela'
		order by 
			col.ORDINAL_POSITION
		";
    //echo $sql;
    $result_set = executa_sql( $sql );
    return resultset_para_tablehtml($result_set, $tabela) . "<BR/><BR/>" . apresenta_estrutura_fks( $base_de_dados, $tabela );
}

function record_set_estrutura_fks( $base_de_dados, $tabela ) {
    global $conexao;
    $sql = "
		select 
			constraint_name
			,table_schema
			,table_name
			,column_name
			,referenced_table_schema
			,referenced_table_name
			,referenced_column_name
		from  
			information_schema.key_column_usage 
		where 
			referenced_table_name is not null 
			and table_name = '$tabela' 
			and table_schema = '$base_de_dados'
		order by 
			constraint_name
		";
    //echo $sql;
	return executa_sql( $sql );
}

function apresenta_estrutura_fks( $base_de_dados, $tabela ) {
    $result_set = record_set_estrutura_fks( $base_de_dados, $tabela );
    return resultset_para_tablehtml($result_set, $tabela);
}


function main() {

    global $conexao;
	global $ini_array;
	
	$html = "";
    $operacao = $_REQUEST['operacao'];
	$consulta = "";
	if ($operacao == "insert") {
		$operacao = "inserir";
	}
	if ($operacao == "update") {
		$operacao = "alterar";
	}
	if (strtolower(substr($operacao, 0, 8)) == "consulta") {
		$consulta = preg_replace('/[^0-9]/', '', $operacao);
		$operacao = "consulta";
	}

    // verifica/inicia conexao
    if (!$conexao) {
        conecta_banco();
		if (isset($_SESSION['base_de_dados'])) {
			seleciona_db($_SESSION['base_de_dados']);
		};
    }
    
    if ($operacao == "autocomplete") {
		echo autocomplete($_SESSION['base_de_dados'], $_SESSION['tabela'], $operacao, $_REQUEST['fk'], $_REQUEST['callback'], $_REQUEST['pesquisa']);
		die();
	}
	
    if ($operacao == "reset_base_de_dados") {
        unset($_SESSION['base_de_dados']);
        unset($_SESSION['tabela']);
        unset($_SESSION['estrutura']);
    }
    if ($operacao == "reset_tabela") {
        unset($_SESSION['tabela']);
        unset($_SESSION['estrutura']);
    }
    
    // verifica base de dados selecionada
    if (!isset($_SESSION['base_de_dados'])) {
        if (!isset($_REQUEST['base_de_dados'])) {
            $html .= seleciona_base_de_dados();
        } else {
            $_SESSION['base_de_dados'] = $_REQUEST['base_de_dados'];
        }
    }
    if (isset($_SESSION['base_de_dados'])) {
        // verifica tabela selecionada
        if (!isset($_SESSION['tabela']) && $operacao != "consulta") {
            if (!isset($_REQUEST['tabela'])) {
                $html .= seleciona_tabela( $_SESSION['base_de_dados'] );
            } else {
                $_SESSION['tabela'] = $_REQUEST['tabela'];
            }
        }
    }
    if (isset($_SESSION['tabela'])) {
		if ($operacao == "inserir") {
			// formulario da tabela selecionada
			$html .= apresenta_formulario( $_SESSION['base_de_dados'], $_SESSION['tabela'], $operacao );
		} elseif ($operacao == "listar") {
			// formulario da tabela selecionada
			$html .= lista_tabela( $_SESSION['base_de_dados'], $_SESSION['tabela'] );
		} else {
			// verifica estrutura da tabela selecionada
			if (!isset($_SESSION['estrutura'])) {
				if (!isset($_REQUEST['estrutura'])) {
					$html .= apresenta_estrutura( $_SESSION['base_de_dados'], $_SESSION['tabela'] );
				} else {
					$_SESSION['estrutura'] = $_REQUEST['estrutura'];
				}
			}
		}
    }
    
    // habilita opções de reset de parametros
    $html = opcoes_de_reset() . $html;

	if (isset($ini_array['consultas']) && !isset($_SESSION['tabela'])) {
		if ($operacao == "consulta") {
			$html = $html . processa_consulta($consulta);
		} else {
			$html = $html . lista_consultas();
		}
	}
	
	echo $html;

}

main();

if ($operacao != "autocomplete") {
	echo "\n<script>\n\t";
	echo "$(\".fk_autocomplete\").each(function() { define_autocomplete(this); });\n";
	echo "</script>\n";
}
?>