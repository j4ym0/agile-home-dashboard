from datetime import datetime
from zoneinfo import ZoneInfo
import re
from typing import Optional, Union

def parse_datetime(date_string: str, default_timezone: str = "UTC" ) -> datetime:
    """
    Parse a datetime string and add timezone if missing.
    
    Args:
        date_string: The datetime string to parse
        default_timezone: Timezone to use

    Returns:
        Timezone-aware datetime
    """

    FORMATS = [
        "%Y/%m/%d %H:%M:%S",
        "%Y-%m-%d %H:%M:%S",
        "%Y/%m/%dT%H:%M:%S",
        "%Y-%m-%dT%H:%M:%S",
        "%Y/%m/%dT%H:%M:%S.%f",
        "%Y-%m-%dT%H:%M:%S.%f",
        "%Y/%m/%dT%H:%M:%S%z",
        "%Y-%m-%dT%H:%M:%S%z",
        "%Y/%m/%dT%H:%M:%S.%f%z",
        "%Y-%m-%dT%H:%M:%S.%f%z",
    ]

    if isinstance(date_string, datetime):
        return date_string
    
    date_string = date_string.strip()

    # Check if it already has timezone info
    has_timezone = bool(re.search(r'[+-]\d{2}:\d{2}$|[+-]\d{4}$|Z$', date_string))
    
    for fmt in FORMATS:
        try:
            dt = datetime.strptime(date_string, fmt)
            
            # Make it timezone-aware
            if not has_timezone:
                dt = dt.replace(tzinfo=ZoneInfo(default_timezone))
            
            if dt.tzinfo is None:
                dt = dt.replace(tzinfo=ZoneInfo(default_timezone))
            
            return dt
        except ValueError:
            continue
        
    raise ValueError(f"Unable to parse datetime string: {date_string}")

def convert_timezone(dt: datetime, target_timezone: str = "UTC", _format: str = None) -> datetime:
    """
    Convert a datetime object to any timezone.
    
    Args:
        dt: The datetime object (must be timezone-aware)
        target_timezone: Target timezone name (e.g., "UTC")
        
    Returns:
        Datetime in the target timezone
    """
    if dt.tzinfo is None:
        raise ValueError("Datetime must be timezone-aware. Use parse_datetime() first.")
    
    target_zone = ZoneInfo(target_timezone)
    if not _format:
        return dt.astimezone(target_zone)
    elif _format == 'ISO':
        return dt.astimezone(target_zone).isoformat()
    else:
        return dt.astimezone(target_zone).strftime(_format)