<?php

class Board extends CI_Controller {
     
    function __construct() {
    		// Call the Controller constructor
	    	parent::__construct();
	    	session_start();
    } 
          
    public function _remap($method, $params = array()) {
	    	// enforce access control to protected functions	
    		
    		if (!isset($_SESSION['user']))
   			redirect('account/loginForm', 'refresh'); //Then we redirect to the index page again
 	    	
	    	return call_user_func_array(array($this, $method), $params);
    }
    
    
    function index() {
		$user = $_SESSION['user'];
    		    	
	    	$this->load->model('user_model');
	    	$this->load->model('invite_model');
	    	$this->load->model('match_model');
	    	
	    	$user = $this->user_model->get($user->login);

	    	$invite = $this->invite_model->get($user->invite_id);
	    	
	    	if ($user->user_status_id == User::WAITING) {
	    		$invite = $this->invite_model->get($user->invite_id);
	    		$otherUser = $this->user_model->getFromId($invite->user2_id);
	    	}
	    	elseif ($user->user_status_id == User::PLAYING) {
	    		$match = $this->match_model->get($user->match_id);
	    		if ($match->user1_id == $user->id) {
	    			$p = 1; 
	    			$otherUser = $this->user_model->getFromId($match->user2_id);
	    		}
	    		else {
	    			$p = 2; 
	    			$otherUser = $this->user_model->getFromId($match->user1_id);
	    		}
	    		
	    		$data['playerIndex'] = $p;
	    	}
	    	
	    	$data['user']=$user;
	    	$data['otherUser']=$otherUser;
	    	
	    	switch($user->user_status_id) {
	    		case User::PLAYING:	
	    			$data['status'] = 'playing';
	    			break;
	    		case User::WAITING:
	    			$data['status'] = 'waiting';
	    			break;
	    	}
	    	
		$this->load->view('match/board',$data);
    }

 	function postMsg() {
 		$this->load->library('form_validation');
 		$this->form_validation->set_rules('msg', 'Message', 'required');
 		
 		if ($this->form_validation->run() == TRUE) {
 			$this->load->model('user_model');
 			$this->load->model('match_model');

 			$user = $_SESSION['user'];
 			 
 			$user = $this->user_model->getExclusive($user->login);
 			if ($user->user_status_id != User::PLAYING) {	
				$errormsg="Not in PLAYING state";
 				goto error;
 			}
 			
 			$match = $this->match_model->get($user->match_id);			
 			
 			$msg = $this->input->post('msg');
 			
 			if ($match->user1_id == $user->id)  {
 				$msg = $match->u1_msg == ''? $msg :  $match->u1_msg . "\n" . $msg;
 				$this->match_model->updateMsgU1($match->id, $msg);
 			}
 			else {
 				$msg = $match->u2_msg == ''? $msg :  $match->u2_msg . "\n" . $msg;
 				$this->match_model->updateMsgU2($match->id, $msg);
 			}
 				
 			echo json_encode(array('status'=>'success'));
 			 
 			return;
 		}
		
 		$errormsg="Missing argument";
 		
		error:
			echo json_encode(array('status'=>'failure','message'=>$errormsg));
 	}
 
	function getMsg() {
 		$this->load->model('user_model');
 		$this->load->model('match_model');
 			
 		$user = $_SESSION['user'];
 		 
 		$user = $this->user_model->get($user->login);
 		if ($user->user_status_id != User::PLAYING) {	
 			$errormsg="Not in PLAYING state";
 			goto error;
 		}
 		// start transactional mode  
 		$this->db->trans_begin();
 			
 		$match = $this->match_model->getExclusive($user->match_id);			
 			
 		if ($match->user1_id == $user->id) {
			$msg = $match->u2_msg;
 			$this->match_model->updateMsgU2($match->id,"");
 		}
 		else {
 			$msg = $match->u1_msg;
 			$this->match_model->updateMsgU1($match->id,"");
 		}

 		if ($this->db->trans_status() === FALSE) {
 			$errormsg = "Transaction error";
 			goto transactionerror;
 		}
 		
 		// if all went well commit changes
 		$this->db->trans_commit();
 		
 		echo json_encode(array('status'=>'success','message'=>$msg));
		return;
		
		transactionerror:
		$this->db->trans_rollback();
		
		error:
		echo json_encode(array('status'=>'failure','message'=>$errormsg));
 	}
 	
 	
 	function getBoard() { 
 		$this->load->model('user_model');
 		$this->load->model('match_model');
 		
 		$user = $_SESSION['user'];
 		
 		$user = $this->user_model->get($user->login);
 		if ($user->user_status_id != User::PLAYING) {
 			$errormsg="Not in PLAYING state";
 			goto error;
 		}
 		
 		$match = $this->match_model->get($user->match_id);
 		$boardstate = $this->match_model->getBoardState($user->match_id);
 
 		$board = $boardstate->board; 
 		$turn = $boardstate->turn; 
		$status = $match->match_status_id; 
 		if ($status != Match::ACTIVE) {
 			goto gameend;
 		}
 	
 		echo json_encode(array('status'=>'success', 'board'=>$board, 'turn'=>$turn));
 		
 		return; 
 		
 		error: 
 		echo json_encode(array('status'=>'failure', 'message'=>$errormsg));
 		return; 
 		
 		gameend:
 		$endmsg = "Game over! \n";
 		if ($status == Match::U1WON) {
 			$winner = $this->user_model->getFromId($match->user1_id);
 			$endmsg = $endmsg. $winner->login. " won!";
 		} elseif ($status == Match::U2WON){
 			$winner = $this->user_model->getFromId($match->user2_id);
 			$endmsg = $endmsg. $winner->login. " won!";
 		} elseif ($status == Match::TIED) {
 			$endmsg = $endmsg. "Tied game!";
 		}
 		echo json_encode(array('status'=>'success', 'end'=>true, 'message' => $endmsg, 'board'=>$board));
 		return; 
 	}
 	
 	function validateMove() { 
 		$this->load->library('form_validation');
 		$this->form_validation->set_rules('col', 'Column', 'required');
 			
 		if ($this->form_validation->run() === TRUE) {
 			$this->load->model('user_model');
 			$this->load->model('match_model');
 		
 			$user = $_SESSION['user'];
 				
 			$user = $this->user_model->getExclusive($user->login);
 			if ($user->user_status_id != User::PLAYING) {
 				$errormsg="Not in PLAYING state";
 				goto error;
 			}
 		
 			$match = $this->match_model->get($user->match_id);
 			$p = ($match->user1_id == $user->id) ? Board_State::U1 : Board_State::U2;
 			
 			$board = $this->match_model->getBoardState($user->match_id);
 			 			
 			// check if user's turn
 			$curBoard = $board->board;
 			$curTurn = $board->turn;
 			if ($curTurn != $p) {
 				$errormsg="Not your turn yet!"; 
 				goto error; 
 			}
 			
 			$col = $this->input->post('col');
 			
 			// get next available empty space
 			$curCol_string = implode($curBoard[$col]);
			$row = strrpos($curCol_string, '0');
			
			// check if column is full
			if ($row === false) {
				$errormsg="The column is full!";
				goto error;
			}
 			
 			$curBoard[$col][$row] = $p;

 			$winning = $p.$p.$p.$p;
 			//check if horizontal win
 			$horizonal = $curBoard[0][$row].$curBoard[1][$row].$curBoard[2][$row].$curBoard[3][$row].$curBoard[4][$row].$curBoard[5][$row].$curBoard[6][$row];
 			$posh = strpos($horizonal, $winning);
 			
 			$vertical =implode($curBoard[$col]);
 			$posv = strpos($vertical, $winning);
 			
 			$s = min($col, $row);
 			$diagonal1 = "";
 			for ($i = 0; $i < 6; $i++) {
 				$x = $col-$s+$i;
 				$y = $row-$s+$i;
 				
 				if ($x > 6 || $y > 5) {
 					break;
 				}
 				$diagonal1 = $diagonal1.$curBoard[$x][$y];
 			}
 			$posd1 = strpos($diagonal1, $winning);
 				
 			$s = min(6-$col, $row);
 			$diagonal2="";
 			for ($i = 0; $i < 6; $i++) {
 				$x = $col+$s-$i;
 				$y = $row-$s+$i;
 				if ($x < 0 || $y > 5) {
 					break;
 				}
 				$c = $curBoard[$x];
 				$r = $c[$y];
 				$diagonal2 = $diagonal2.$r;

 			}
 			$posd2 = strpos($diagonal2, $winning);
 			
 			$newBoard = new Board_State();
 			$newBoard->board = $curBoard; 
 			$newBoard->turn = ($curTurn == Board_State::U1) ? Board_State::U2 : Board_State::U1;
 			
 			$this->db->trans_begin(); 
 			$this->match_model->updateBoardState($user->match_id, $newBoard);
 			
 			if ($this->db->trans_status() === false) {
 				$this->db->trans_rollback(); 
 				$errormsg = "database error";
 				goto error; 
 			}
 			else {
 				$this->db->trans_commit(); 	
 			}
 			
 			if ($posh !== false || $posv !== false || $posd1 !== false || $posd2 !== false) {
 				goto win;
 			}
 			
 			$cols = '';
 			foreach ($curBoard as $col) {
 				$cols = $cols.implode($col);	
 			}
 			if (strpos($cols, '0') === false ){
 				goto tied;
 			}
 			echo json_encode(array('status'=>'success', 'col'=>$col, 'row'=>$row, 'diagonal1'=>$diagonal1, 'diagonal2'=>$diagonal2));
 				
 			return;
 		}
 		
 		$errormsg="Missing argument";
 		
 		error:
 		echo json_encode(array('status'=>'failure','message'=>$errormsg));
 		return; 
 		
 		win: 
 		$status = ($p == 1) ? Match::U1WON : Match::U2WON; 
 		$this->match_model->updateStatus($user->match_id, $status);
 		echo json_encode(array('status'=>'success'));
 		return; 
 		
 		tied: 
 		$this->match_model->updateStatus($user->match_id, Match::TIED);
 		echo json_encode(array('status'=>'success'));
 		//handle winning condition here
 	}
 	
 }

