import { Module } from '@nestjs/common';
import { MapConfigController } from './map-config.controller';
import { MapConfigService } from './map-config.service';

@Module({ controllers: [MapConfigController], providers: [MapConfigService] })
export class MapConfigModule {}
