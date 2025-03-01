<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\StreamInput;

use App\Component\Utility;
use App\Component\Facades\Facade\AppInterfacesFacade as AppInterfaces;

use App\Component\NginxConfigGen;

class NginxPowerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nginxrtmp:power';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Power control Nginx process';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $binaryProc = [
            'nginxBinName' => 'nginx.exe',
            'nginxPath' => ((AppInterfaces::getsetting('IS_CUSTOM_NGINX_BINARY') == TRUE && !empty(AppInterfaces::getsetting('NGINX_BINARY_DIRECTORY'))) ? AppInterfaces::getsetting('NGINX_BINARY_DIRECTORY') : Utility::defaultBinDirFolder('nginx'))
        ];

        # check nginx executable path if not found then exit or else continue
        if (!file_exists($binaryProc['nginxPath'] . DIRECTORY_SEPARATOR . $binaryProc['nginxBinName'])) {
            return $this->error('Nginx not found: ' . $binaryProc['nginxPath']);
        } else {
            # check nginx process if running then restart nginx service
            if (Utility::getInstanceRunByPath($binaryProc['nginxPath'] . DIRECTORY_SEPARATOR . $binaryProc['nginxBinName'], $binaryProc['nginxBinName'])['found_process']) {
                # Set is live in database to false / offline if nginx process is turn off
                $stream_db = StreamInput::where(['is_live' => TRUE]);
                if ($stream_db->exists()) {
                    $stream_db->update(['is_live' => FALSE]);
                }

                # stop nginx service
                try {
                    Utility::runInstancewithPid('cmd /c start /B "" /d"' . $binaryProc['nginxPath'] . '" "' . $binaryProc['nginxBinName'] . '" -s stop');
                    return $this->info('Power Off Nginx successfully');
                } catch (\Throwable $e) {
                    return $this->error('Power Off Nginx unsuccessfully, Error:' . $e->getMessage());
                }
            } else {
                # Check if nginx config file is exist or not if not then create it.
                if (!file_exists($binaryProc['nginxPath'] . '\conf\nginx.conf')) {
                    try {
                        NginxConfigGen::GenerateBaseConfig();
                    } catch (\Exception $e) {
                        return $this->error('Stream input Nginx config cannot be generated, Error: ' . $e->getMessage());
                    }
                }

                # Turn on nginx process if it is off
                try {
                    Utility::runInstancewithPid('cmd /c start /B "" /d"' . $binaryProc['nginxPath'] . '" "' . $binaryProc['nginxBinName'] . '"');
                    return $this->info('Power On Nginx successfully');
                } catch (\Throwable $e) {
                    return $this->error('Power On Nginx unsuccessfully, Error:' . $e->getMessage());
                }
            }
        }
    }
}
