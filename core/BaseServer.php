<?php
namespace Game\Core;

/**
 * ���������࣬��������Ϊwebsocket��ͬʱ���Լ���tcp��http������
 *
 */  
abstract class BaseServer {
	/**
	 * ��������
	 */         
	private static  $_instance = null;
    
	/**
	 * websocket����������
	 */              
	protected $server = null;
    
	/**
	 * tcp����������
	 */         
	protected $tcpserver = null;

	/**
	 * ������ip
	 */         
	protected $server_ip = "0.0.0.0";
    
	/**
	 * websocket�������˿�
	 */         
	protected $server_port = 9502;
    
	/**
	 * tcp�������˿ڣ��˶˿�����̳��и�ֵ�� �������tcp�˿�
	 */         
	protected $tcpserver_port = 0;
	
	/**
	 * http�������˿ڣ��˶˿�����̳��и�ֵ�� �������tcp�˿�
	 */         
	protected $httpserver_port = 0;
	
	/**
	 * ��������������ǰ׺
	 */
	protected $process_name_prefix = 'game';

	/**
	 * websocket����������
	 */         
	protected $config = array(
		'dispatch_mode' => 3,
		'open_length_check' => 1,
		'package_length_type' => 'N',
		'package_length_offset' => 0,
		'package_body_offset' => 4,

		'package_max_length' => 2097152, // 1024 * 1024 * 2,
		'buffer_output_size' => 3145728, //1024 * 1024 * 3,
		'pipe_buffer_size' => 33554432, // 1024 * 1024 * 32,		
		
		'heartbeat_check_interval' => 30,
		'heartbeat_idle_time' => 60,
		
//		'open_cpu_affinity' => 1,

//		'reactor_num' => 32,//��������ΪCPU���� x 2 �°���Զ����� cpu����
		'max_conn'=>2000,
		'worker_num' => 1,
		'task_worker_num' => 2,//����������Ӵ󣬽���1000

		'max_request' => 0, //��������Ϊ0������ᵼ�²�������ʱ,don't change this number
		'task_max_request' => 2000,
		
//		'daemonize'=>1, 
//		'log_level' => 2, //swoole ��־���� Info
		'backlog' => 3000,
		'log_file' => '../log/sw_server.log',//swoole ϵͳ��־���κδ�����echo�������������
//		'task_tmpdir' => '/dev/shm/swtask/',//task Ͷ�����ݹ���ʱ������ʱ����������뽫tmp����ʹ���ڴ�

//		'document_root' => '/data/web/test/myswoole/poker/client',
//		'enable_static_handler' => true,
	);

	/**
	 * tcp����������
	 */         
	protected $tcp_config = array(

	);
	
	/**
	* ����ģʽ����ֹ���󱻿�¡
	*/
	private function __clone() {}

	/**
	* ����ģʽ����ֹ���󱻿�¡
	*/
	private function __construct() {}

	/**
	* ��ȡ��������
	* @param int uid �û�UID
	* @param string token �û�Token
	* @return object
	*/
	public static function getInstance() {
		if (self::$_instance == null) {
			self::$_instance = new static();           
		}
		return self::$_instance;
	}

	/**
	 * ��ʼ��������
	 */              
	public function initServer() {
		
		//����websocket������
		$this->server = new \Swoole\Websocket\Server($this->server_ip, $this->server_port);	
				
		//���tcp�˿������ã� ������tcpЭ��
		if(!empty($this->tcpserver_port)) {			
			//tcp server
			$this->tcpserver = $this->server->listen($this->server_ip, $this->tcpserver_port, SWOOLE_SOCK_TCP);
			//tcpֻʹ���⼸�����¼�
			$this->tcpserver->on('Connect', array($this, 'onConnect'));
			$this->tcpserver->on('Receive', array($this, 'onReceive'));
		}
		
		//���http�˿������ã� ������httpЭ��
		if(!empty($this->httpserver_port)) {			
			//tcp server
			$this->tcpserver = $this->server->listen($this->server_ip, $this->httpserver_port, SWOOLE_SOCK_TCP);
			//http������ֻʹ������¼�
			$this->server->on('Request', array($this, 'onRequest'));
		}
		
		//init websocket server
		$this->server->on('Start', array($this, 'onStart'));
		$this->server->on('ManagerStart', array($this, 'onManagerStart'));
		$this->server->on('ManagerStop', array($this, 'onManagerStop'));
		//websocket������
		$this->server->on('Open', array($this, 'onOpen'));
		$this->server->on('Message', array($this, 'onMessage'));		
		$this->server->on('WorkerStart', array($this, 'onWorkerStart'));
		$this->server->on('WorkerError', array($this, 'onWorkerError'));
		$this->server->on('Task', array($this, 'onTask'));
		$this->server->on('Finish', array($this, 'onFinish'));
		$this->server->on('Close', array($this, 'onClose'));
		$this->init($this->server);
		return self::$_instance;
	}

	/**
	 * ������������ʼ�������磺such as swoole atomic table or buffer ���Է���swoole�ļ�������table��
	*/
	abstract protected function init($serv);

	/**
	 * WorkerStartʱ����Ե��ã� //require_once() ��Ҫ���صĴ����������� what's you want load (such as framework init)
	 * ������Ҫ��̬���صĶ��������������޷������߼�
	*/
	abstract protected function initReload($server, $worker_id);
	
	/**
	 * ��ҵ����Ҫ�ǿ���tcpЭ��ʱ�����ã�ҵ��ʵ�ʴ�����������return the result ʹ��return���ش�����//throw new Exception("asbddddfds",1231);
	*/
	abstract protected function doWork($serv, $task_id, $src_worker_id, $data); 
    
	/**
     * ����������
     */         
	public function start() {
		$this->server->set($this->config);
		//���tcp�˿������ã� ������tcpЭ��
		if(!empty($this->tcpserver_port)) {
			//ע�⣬ ������˿ڣ�һ����Ҫ���Ե���set������recive�ص������Ż���Ч�� ��������ã� �ص�����Ч
			$this->tcpserver->set($this->tcp_config);
		}
		$this->server->start();
	}
	
	//����ʼ�ص�
	public function onStart($serv) {
		swoole_set_process_name($this->process_name_prefix."_master_".get_called_class());
		echo "MasterPid={$serv->master_pid}\n";
		echo "ManagerPid={$serv->manager_pid}\n";
		echo "Server: start.Swoole version is [" . SWOOLE_VERSION . "]\n";	
	}
	
	//������������ص�
	public function onManagerStart($serv) {
		swoole_set_process_name($this->process_name_prefix."_manager_".get_called_class());
		echo "onManagerStart:\n";	
	}
	
	//������̹رջص�
	public function onManagerStop($serv) {
		$serv->shutdown();
		echo "onManagerStop:\n";	
	}
	
	//ws���ӻص�
	public function onOpen($serv, $frame) {
		echo "onOpen connection open: ".$frame->fd."\n";
	}
	
	//tcp���ӻص�
	public function onConnect($serv, $fd) {
		echo "onConnect: connected...\n";
	}
	
	//wsͶ������
	public function onMessage($serv, $frame) {
		$send['protocol'] = 'ws';
		$send['data'] = $frame->data;
		$send['fd'] = $frame->fd;
		$taskid = $this->server->task($send, -1, function ($serv, $task_id, $data) use ($frame) {
			if(!empty($data)) {
				$serv->push($frame->fd, $data, WEBSOCKET_OPCODE_BINARY);
			}				
		});	
	}
	
	//httpͶ������
	public function onRequest($request, $response) {
		$send['protocol'] = 'http';
		$send['data'] = $request;
		$send['response'] = $response;
		$taskid = $this->server->task($send, -1, function ($serv, $task_id, $data) use ($response) {
			if(!empty($data)) {
				$response->end($data);
			}				
		});	
		echo "Request: Start";
	}
	
	//tcpͶ������
	public function onReceive($serv, $fd, $from_id, $data) {
		$send['protocol'] = 'tcp';
		$send['data'] = $data;
		$send['fd'] = $fd;
		$taskid = $this->server->task($send, -1, function ($serv, $task_id, $data) use ($fd) {
			if(!empty($data)) {
				$serv->send($fd, $data);
			}				
		});	
		echo "onReceive: ".$data;
	}
	
	//worker���̿����ص�
	public function onWorkerStart($server, $worker_id) {
		$istask = $server->taskworker;	
        if ($istask) {
			$this->initReload($server, $worker_id);
			swoole_set_process_name($this->process_name_prefix."_task{$worker_id}_".get_called_class());
			echo "Task work_id is {$worker_id}\n"; 	
        } else {
			swoole_set_process_name($this->process_name_prefix."_worker{$worker_id}_".get_called_class());
			echo "Worker work_id is {$worker_id}\n"; 
		}
		echo "onWorkerStart:\n";	
	}
	
	//worker���̴���ص�
	public function onWorkerError($server, $worker_id, $worker_pid, $exit_code) {
		echo "onWorkerError: worker_id={$worker_id}  worker_pid={$worker_pid}  exit_code={$exit_code}\n";	
	}
	
	//������̻ص�
	public function onTask($serv, $task_id, $src_worker_id, $data) {
		$data = $this->doWork($serv, $task_id, $src_worker_id, $data);
		echo "onTask: task_id={$task_id}	woker_id={$src_worker_id}\n";
		return $data;
	}
	
	//���������ص����� �߰汾�����Զ���ص�����
	public function onFinish($serv, $task_id, $data) {
		echo "onFinish:\n";	
	}
	
	//�������رջص�
	public function onClose($serv, $fd) {
		echo "onClose connection close: ".$fd."\n";
	}
	
	public function __destruct() {
        echo "Server Was Shutdown..." . PHP_EOL;
        //shutdown
        $this->server->shutdown();
    }
}
