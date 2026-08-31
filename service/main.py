import schedule
import time
import logging
import os
from datetime import datetime, time as dt_time
import pytz
from typing import Optional
from config import Config
from octopus import Octopus
from settings import Settings
from db_handler import DBHandler

# Get log level from environment variable
log_level = os.getenv('LOG_LEVEL', 'INFO')
level = getattr(logging, log_level.upper(), logging.INFO)

# Setup logging
logging.basicConfig(
    level=level,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('scheduled_service.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# Initialize config
config = Config.get_instance()
_db = DBHandler()

class ScheduledService:
    def __init__(self, timezone: str = 'UTC'):
        """
        Initialize the scheduled service
        
        Args:
            timezone: Timezone string (e.g., 'UTC', 'US/Eastern', 'Europe/London')
        """
        self.timezone = pytz.timezone(timezone)
        self.running = True
        self.last_execution_times = {}
        
    def task_every_minute(self):
        """
        Task 1: Runs every minute
        """
        current_time = datetime.now(self.timezone).strftime("%Y-%m-%d %H:%M:%S")
        logger.info(f"[MINUTE TASK] Executed at: {current_time}")
                
        try:

            pass
            
        except Exception as e:
            logger.error(f"Error in minute task: {e}")
            
    def task_every_30_minutes(self):
        """
        Task 2: Runs every 30 minutes (at :00 and :30)
        """
        current_time = datetime.now(self.timezone).strftime("%Y-%m-%d %H:%M:%S")
        logger.info(f"[30-MINUTE TASK] Executed at: {current_time}")
        
        try:

            pass
            
        except Exception as e:
            logger.error(f"Error in 30-minute task: {e}")
            
    def task_daily_4pm(self):
        """
        Task 3: Runs daily at 4:00 PM
        """
        current_time = datetime.now(self.timezone).strftime("%Y-%m-%d %H:%M:%S")
        logger.info(f"[DAILY TASK] Executed at: {current_time}")

        try:
            if not Settings.octopus_configured:
                logger.debug(f"Octopus not setup")
            else:
                if not Settings.background_gathering:
                    logger.debug(f"background gathering not enabled")
                else:

                    if Settings.save_tariff_data or save_standard_tariff_data or settings.save_consumption_data:
                        logger.info(f"Init Cotopus")
                        octopus = Octopus(_db)
                    else:
                        logger.debug(f"All save settings are disabled")

                    if Settings.save_tariff_data:
                        logger.info(f"Fetchign tariff data from Octopus API")
                        octopus.getTariffData(Settings.electricity_product_code, Settings.electricity_tariff_code)
                    else:
                        logger.debug(f"Save tariff data to database not enabled")

                    if Settings.save_standard_tariff_data:
                        logger.info(f"Fetching standard tariff data from Octopus API")
                        octopus.getStandardTariff(Settingselectricity_tariff_code)
                    else:
                        logger.debug(f"Save standard tariff data to database not enabled")

                    if Settings.save_consumption_data:
                        logger.info(f"Fetching consumption data from Octopus API")
                        octopus.getConsumptionData(Settings.electricity_meter_MPAN, Settings.electricity_meter_serial)
                    else:
                        logger.debug(f"Save consumption data to database not enabled")

            pass
            
        except Exception as e:
            logger.error(f"Error in daily task: {e}")
    
#    def task_every_15_minutes(self):
#        """
#        Additional example: Runs every 15 minutes
#        """
#        current_time = datetime.now(self.timezone).strftime("%Y-%m-%d %H:%M:%S")
#        logger.info(f"[15-MINUTE TASK] Executed at: {current_time}")
#        # Your logic here
#        
#    def task_every_hour(self):
#        """
#        Additional example: Runs every hour at :00
#        """
#        current_time = datetime.now(self.timezone).strftime("%Y-%m-%d %H:%M:%S")
#        logger.info(f"[HOURLY TASK] Executed at: {current_time}")
#        # Your logic here
#        
#      def task_weekdays_9am(self):
#        """
#        Additional example: Runs only on weekdays at 9 AM
#        """
#        current_time = datetime.now(self.timezone).strftime("%Y-%m-%d %H:%M:%S")
#        logger.info(f"[WEEKDAY 9AM TASK] Executed at: {current_time}")
#        # Your logic here
        
    def setup_schedules(self):

        # for smart device in future
        #schedule.every(1).minutes.do(self.task_every_minute)
        #logger.info("Scheduled: Every minute task")

        schedule.every(30).minutes.do(self.task_every_30_minutes)
        logger.info("Scheduled: 30 minutes task")

        schedule.every().day.at("16:00").do(self.task_daily_4pm)
        logger.info("Scheduled: Daily at 4:00 PM task")
        
        # Extra tasks for the future / reports
        # schedule.every(15).minutes.do(self.task_every_15_minutes)
        # schedule.every().hour.do(self.task_every_hour)
        # schedule.every().day.at("09:00").do(self.task_weekdays_9am)
        # schedule.every().monday.at("09:00").do(self.weekly_monday_task)
        # schedule.every().wednesday.at("14:30").do(self.weekly_wednesday_task)

        logger.info("All schedules configured successfully")
    
    # ============ SERVICE CONTROL ============
    
    def run(self):
        logger.info(f"Service started with timezone: {self.timezone.zone}")
        self.setup_schedules()
        
        try:
            while self.running:
                schedule.run_pending()
                time.sleep(1)
                
        except KeyboardInterrupt:
            logger.info("Service stopped by user (Ctrl+C)")
            self.running = False
        except Exception as e:
            logger.error(f"Service error: {e}")
            raise
        finally:
            logger.info("Service shutdown complete")
            
    def stop(self):
        # Gracefully stop
        logger.info("Stopping service...")
        self.running = False


if __name__ == "__main__":
    service = ScheduledService(timezone = Config.get('app.timezone', 'UTC'))
    service.run()
