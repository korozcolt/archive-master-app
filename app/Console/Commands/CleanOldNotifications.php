<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CleanOldNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:clean 
                            {--days=30 : Number of days to keep notifications}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--type= : Specific notification type to clean}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean old notifications from the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $type = $this->option('type');
        
        $this->info("Cleaning notifications older than {$days} days...");
        
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be deleted');
        }
        
        $cutoffDate = Carbon::now()->subDays($days);
        
        try {
            // Build query
            $query = DB::table('notifications')
                ->where('created_at', '<', $cutoffDate);
                
            if ($type) {
                $query->where('type', 'like', "%{$type}%");
                $this->info("Filtering by notification type: {$type}");
            }
            
            // Get count before deletion
            $count = $query->count();
            
            if ($count === 0) {
                $this->info('No old notifications found to clean.');
                return self::SUCCESS;
            }
            
            $this->info("Found {$count} notifications to clean.");
            
            if ($dryRun) {
                // Show sample of what would be deleted
                $sample = $query->limit(5)->get(['id', 'type', 'created_at']);
                
                $this->table(
                    ['ID', 'Type', 'Created At'],
                    $sample->map(function ($notification) {
                        return [
                            $notification->id,
                            class_basename($notification->type),
                            $notification->created_at
                        ];
                    })->toArray()
                );
                
                if ($count > 5) {
                    $this->info("... and " . ($count - 5) . " more notifications.");
                }
                
                $this->info("Would delete {$count} notifications (DRY RUN).");
                return self::SUCCESS;
            }
            
            // Confirm deletion
            if (!$this->confirm("Are you sure you want to delete {$count} notifications?")) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
            
            // Perform deletion in chunks to avoid memory issues
            $deleted = 0;
            $chunkSize = 1000;
            
            $this->output->progressStart($count);
            
            while (true) {
                $chunk = $query->limit($chunkSize)->pluck('id');
                
                if ($chunk->isEmpty()) {
                    break;
                }
                
                $chunkDeleted = DB::table('notifications')
                    ->whereIn('id', $chunk)
                    ->delete();
                    
                $deleted += $chunkDeleted;
                $this->output->progressAdvance($chunkDeleted);
                
                // Small delay to prevent overwhelming the database
                usleep(100000); // 0.1 seconds
            }
            
            $this->output->progressFinish();
            
            $this->info("Successfully deleted {$deleted} old notifications.");
            
            // Log the cleanup
            \Log::info('Old notifications cleaned', [
                'deleted_count' => $deleted,
                'days_threshold' => $days,
                'type_filter' => $type,
                'executed_by' => 'console'
            ]);
            
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Failed to clean old notifications: ' . $e->getMessage());
            
            \Log::error('Failed to clean old notifications', [
                'error' => $e->getMessage(),
                'days_threshold' => $days,
                'type_filter' => $type
            ]);
            
            return self::FAILURE;
        }
    }
}
