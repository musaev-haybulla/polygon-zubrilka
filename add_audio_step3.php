<?php
// Step 3: Audio markup (timings)
// This page is a frontend shell that talks to /api/timings endpoints.
// Open as: add_audio_step3.php?track_id=123
?><!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Zubrilka Grok — Разметка</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    #waveform {
      width: 100%;
      border: 1px solid #dee2e6;
      border-radius: .25rem;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body class="bg-light">
  <div class="container py-5" x-data="project()">
    <div class="card">
      <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Разметка аудио</h5>
      </div>
      <div class="card-body">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="text-muted">Масштаб</span>
          <button class="btn btn-sm btn-outline-secondary" @click="zoomOut" title="Уменьшить">−</button>
          <input type="range" min="20" max="300" step="10" x-model.number="zoom" @input="applyZoom" style="width: 220px;">
          <button class="btn btn-sm btn-outline-secondary" @click="zoomIn" title="Увеличить">+</button>
          <button class="btn btn-sm btn-outline-secondary ms-2" @click="fitAll" title="Показать весь трек">Fit All</button>
          <button class="btn btn-sm btn-outline-secondary" @click="fitRegion" :disabled="!currentRegion" title="По региону">Fit Region</button>
        </div>
        <div id="waveform"></div>

        <div class="d-flex align-items-center justify-content-center gap-2 p-3 mb-3 bg-secondary-subtle border rounded">
          <button class="btn btn-primary" @click="selectPreviousLine" :disabled="isFirstLine">← Назад</button>
          <span class="badge text-bg-dark fs-6" x-text="effectiveRegion.startTime"></span>
          <span class="flex-grow-1 text-center p-2 border rounded bg-white text-truncate" style="min-height: 40px;" x-text="effectiveRegion.text"></span>
          <span class="badge text-bg-dark fs-6" x-text="effectiveRegion.endTime"></span>
          <button class="btn btn-primary" @click="selectNextLine" :disabled="isLastLine">Вперед →</button>
        </div>

        <div class="d-flex justify-content-center flex-wrap gap-2">
          <button class="btn btn-success" @click="togglePlayPause">
            <span x-text="isPlaying ? 'Пауза' : 'Плей'"></span>
          </button>
          <button class="btn btn-warning" @click="saveCurrentRegion" :disabled="isLastLine">Фиксация черновика</button>
          <button class="btn btn-primary" @click="finalizeTimings" :disabled="!isAllComplete">Сохранить озвучку</button>
        </div>

        <div class="list-group mt-4">
          <template x-for="(line, index) in lines" :key="line.id">
            <label class="list-group-item d-flex gap-3 align-items-center">
              <input class="form-check-input m-0" type="radio" :name="radioGroup" :value="index" @change="selectLine(index)" :checked="index === selectedIndex" :disabled="lines[index].startTime === null">
              <span class="flex-grow-1" x-text="line.text"></span>
              <div class="input-group" style="width: 220px;">
                <input type="number" class="form-control" step="0.01" x-model.number="lines[index].startTime" @input="validateTime(index, 'start')" :disabled="index === 0" title="Start time">
                <input type="number" class="form-control" step="0.01" x-model.number="lines[index].endTime" @input="validateTime(index, 'end')" :disabled="index === lines.length - 1" title="End time">
              </div>
            </label>
          </template>
        </div>
      </div>
      <div class="card-footer text-muted text-center">
        © 2024 Zubrilka Grok
      </div>
    </div>
  </div>

  <!-- Toast container -->
  <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
    <div id="appToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body" id="appToastBody">Операция выполнена</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <script src="https://unpkg.com/wavesurfer.js@7"></script>
  <script src="https://unpkg.com/wavesurfer.js@7/dist/plugins/regions.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    function project() {
      return {
        // Constants
        MIN_DURATION: 0.5,
        FIRST_WINDOW_FACTOR: 5,
        API_INIT: 'api/timings/init.php',
        API_SAVE: 'api/timings/line.php',
        API_FINALIZE: 'api/timings/finalize.php',
        basePath: '',

        // Data
        ZOOM_MIN: 20,
        ZOOM_MAX: 300,
        ZOOM_STEP: 10,
        zoom: 60,
        wsReady: false,
        trackId: null,
        pausesArray: [],
        lines: [], // [{ id, text, line_number, startTime, endTime }]
        selectedIndex: 0,
        radioGroup: 'lines',
        isPlaying: false,
        totalDuration: 0,
        audioUrl: '',

        // Wavesurfer
        wavesurfer: null,
        wsRegions: null,
        currentRegion: null,

        // Derived
        get effectiveRegion() {
          const line = this.lines[this.selectedIndex];
          if (!line) return { startTime: 0, text: '', endTime: 0 };
          if (this.currentRegion) {
            return {
              startTime: this.formatTime(this.currentRegion.start),
              text: line.text,
              endTime: this.formatTime(this.currentRegion.end),
            };
          }
          return {
            startTime: this.formatTime(line.startTime),
            text: line.text,
            endTime: this.formatTime(line.endTime),
          };
        },

        // Normalize pause hints from backend: use pause_detection only
        extractPauses(payload) {
          try {
            if (!payload || typeof payload !== 'object') return [];
            const pd = payload.pause_detection;
            let cand = null;
            if (pd) {
              if (Array.isArray(pd)) cand = pd; // pause_detection as array
              else if (typeof pd === 'object') {
                if (Array.isArray(pd.splits)) cand = pd.splits;
                else if (Array.isArray(pd.pauses)) cand = pd.pauses;
                else if (Array.isArray(pd.breaks)) cand = pd.breaks;
              }
            }
            return Array.isArray(cand) ? cand.map(parseFloat).filter(n => Number.isFinite(n)) : [];
          } catch (_) { return []; }
        },
        get isFirstLine() { return this.selectedIndex === 0; },
        get isLastLine() { return this.selectedIndex === this.lines.length - 1; },
        get remainingLinesCount() { return this.lines.length - this.selectedIndex - 1; },
        get isAllComplete() {
          if (!Array.isArray(this.lines) || this.lines.length === 0) return false;
          let prevEnd = 0;
          for (let i = 0; i < this.lines.length - 1; i++) {
            const l = this.lines[i];
            if (typeof l.startTime !== 'number' || typeof l.endTime !== 'number') return false;
            if (!(l.startTime >= prevEnd)) return false;
            if (!(l.endTime > l.startTime)) return false;
            prevEnd = l.endTime;
          }
          // Last line implicitly ends at totalDuration
          return this.totalDuration > prevEnd;
        },

        // Init
        async init() {
          try {
            const url = new URL(window.location.href);
            this.basePath = url.pathname.replace(/add_audio_step3\.php$/, '');
          } catch (e) {
            console.error(e);
          }
          this.trackId = this.getIdFromUrl();
          if (!this.trackId) {
            alert('id отсутствует в URL');
            return;
          }
          await this.loadInit();
          this.initWaveSurfer();
          this.setupWatchers();
        },

        getIdFromUrl() {
          const p = new URLSearchParams(window.location.search);
          const id = parseInt(p.get('id'), 10);
          return Number.isFinite(id) && id > 0 ? id : null;
        },

        async loadInit() {
          const res = await fetch(`${this.basePath}${this.API_INIT}?id=${this.trackId}`);
          const json = await res.json();
          if (!json || !json.ok) throw new Error(json?.error || 'Init failed');
          const data = json.data;
          this.audioUrl = data.audioUrl;
          this.totalDuration = parseFloat(data.totalDuration);
          this.pausesArray = this.extractPauses(data);

          // Build lines with start/end from timings
          const timings = data.timings || {}; // map line_id -> end
          const linesRaw = data.lines || [];
          // Normalize and compute starts
          this.lines = linesRaw.map(l => ({ id: l.id, text: l.text, line_number: l.line_number, startTime: null, endTime: null }));
          // Fill end from timings (backend already included last line with end=duration)
          const byId = new Map(this.lines.map(l => [l.id, l]));
          for (const [k, v] of Object.entries(timings)) {
            const lid = parseInt(k, 10);
            const line = byId.get(lid);
            if (line) line.endTime = this.formatTime(parseFloat(v));
          }
          // Compute starts by walking
          this.lines.sort((a,b) => a.line_number - b.line_number);
          for (let i = 0; i < this.lines.length; i++) {
            if (i === 0) {
              this.lines[i].startTime = 0.0;
            } else {
              const prev = this.lines[i-1];
              this.lines[i].startTime = prev.endTime !== null ? prev.endTime : null;
            }
            // If no endTime and not last — hint from pauses
            if (this.lines[i].endTime === null && i < this.lines.length - 1) {
              const hint = this.getNextPause(this.lines[i].startTime ?? 0);
              this.lines[i].endTime = hint;
            }
            // Ensure last line end equals totalDuration (domain rule already applied, but be safe)
            if (i === this.lines.length - 1) {
              this.lines[i].endTime = this.formatTime(this.totalDuration);
            }
          }

          // If there is no pause_detection, prefill only the first line with a small default window
          if ((!Array.isArray(this.pausesArray) || this.pausesArray.length === 0) && this.lines.length > 0) {
            const first = this.lines[0];
            if (first.endTime === null) {
              const start0 = typeof first.startTime === 'number' ? first.startTime : 0;
              const proposed = this.formatTime(Math.min(this.totalDuration, start0 + this.MIN_DURATION * 5));
              first.endTime = proposed;
              if (this.lines.length > 1) this.lines[1].startTime = proposed;
            }
          }
        },

        // WaveSurfer
        initWaveSurfer() {
          this.wsRegions = WaveSurfer.Regions.create();
          this.wavesurfer = WaveSurfer.create({
            container: '#waveform',
            waveColor: '#a0c4ff',
            progressColor: '#4361ee',
            cursorColor: '#2b2d42',
            height: 120,
            normalize: true,
            plugins: [this.wsRegions],
            // Отключаем авто-прокрутку и авто-центрирование, чтобы не дёргалось
            autoScroll: false,
            autoCenter: false,
          });
          // zoom будет применён после загрузки (в 'ready')

          // Ctrl/Cmd + колесо мыши для зума
          const wfEl = document.getElementById('waveform');
          wfEl.addEventListener('wheel', (e) => {
            if (e.ctrlKey || e.metaKey) {
              e.preventDefault();
              if (e.deltaY > 0) this.zoomOut(); else this.zoomIn();
            }
          }, { passive: false });

          this.wavesurfer.load(this.audioUrl);

          this.wavesurfer.on('ready', () => {
            this.wsReady = true;
            this.totalDuration = this.wavesurfer.getDuration();
            // Применяем стартовый масштаб только когда аудио загружено
            this.applyZoom();
            if (this.lines.length > 0) this.selectLine(0);
          });

          this.wavesurfer.on('timeupdate', () => {
            if (this.currentRegion && this.isPlaying) {
              const currentTime = this.wavesurfer.getCurrentTime();
              const { end } = this.currentRegion;
              if (currentTime >= end) {
                this.wavesurfer.stop();
                this.isPlaying = false;
                this.wavesurfer.setTime(this.currentRegion.start);
              }
            }
          });
        },

        setupWatchers() {
          this.lines.forEach((_, index) => {
            this.$watch(`lines[${index}].endTime`, () => {
              this.updateRegionFromModel(index);
              this.syncLineTimes(index, 'end');
              // Autosave for non-last lines
              if (index < this.lines.length - 1) this.debouncedSave(index);
            });

            this.$watch(`lines[${index}].startTime`, () => {
              this.updateRegionFromModel(index);
              this.syncLineTimes(index, 'start');
            });
          });
        },

        // Debounce save
        _saveTimer: null,
        debouncedSave(index) {
          clearTimeout(this._saveTimer);
          this._saveTimer = setTimeout(() => this.saveLine(index).catch(err => this.notifyError(err)), 400);
        },

        async saveLine(index) {
          const line = this.lines[index];
          if (!line || line.endTime == null) return;
          const payload = { id: this.trackId, line_id: line.id, end_time: line.endTime };
          const res = await fetch(`${this.basePath}${this.API_SAVE}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
          });
          const json = await res.json();
          if (!res.ok || !json.ok) throw new Error(json?.error || 'Save failed');
        },

        async finalizeTimings() {
          try {
            if (!this.isAllComplete) return;
            const payload = { id: this.trackId };
            const res = await fetch(`${this.basePath}${this.API_FINALIZE}`, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify(payload),
            });
            const json = await res.json();
            if (!res.ok || !json.ok) throw new Error(json?.error || 'Finalize failed');
            this.showToast('Озвучка сохранена');
            setTimeout(() => { window.location.href = `${this.basePath}poem_list.php`; }, 1200);
          } catch (e) {
            this.notifyError(e);
          }
        },

        showToast(message) {
          try {
            const el = document.getElementById('appToast');
            const body = document.getElementById('appToastBody');
            if (!el || !body) return alert(message);
            body.textContent = message;
            const toast = new bootstrap.Toast(el, { delay: 1000 });
            toast.show();
          } catch (_) {
            alert(message);
          }
        },

        notifyError(err) {
          console.error(err);
          alert(err.message || 'Ошибка сохранения');
        },

        // Constraints helpers
        getLineConstraints(index) {
          const constraints = {};
          const isLastLine = index === this.lines.length - 1;

          if (index > 0) {
            const prevLine = this.lines[index - 1];
            constraints.minStart = prevLine.startTime !== null ? prevLine.startTime + this.MIN_DURATION : 0;
          } else {
            constraints.minStart = 0;
          }

          constraints.maxStart = this.lines[index].endTime !== null ? this.lines[index].endTime - this.MIN_DURATION : Infinity;

          constraints.minEnd = this.lines[index].startTime !== null ? this.lines[index].startTime + this.MIN_DURATION : 0;

          if (isLastLine) {
            constraints.maxEnd = this.totalDuration;
            constraints.minEnd = Math.max(constraints.minEnd, this.totalDuration - this.MIN_DURATION);
          } else {
            const remainingLines = this.lines.length - index - 1;
            const reservedTime = remainingLines * this.MIN_DURATION;
            constraints.maxEnd = Math.min(
              this.totalDuration - reservedTime,
              this.lines[index + 1].endTime !== null ? this.lines[index + 1].endTime - this.MIN_DURATION : Infinity,
            );
          }
          return constraints;
        },

        // Time sync
        syncLineTimes(index, type) {
          if (type === 'end' && index < this.lines.length - 1) {
            if (this.lines[index].endTime !== null) this.lines[index + 1].startTime = this.lines[index].endTime;
          } else if (type === 'start' && index > 0) {
            if (this.lines[index].startTime !== null) this.lines[index - 1].endTime = this.lines[index].startTime;
          }
        },

        updateLineTime(index, newStart, newEnd) {
          this.lines[index].startTime = this.formatTime(newStart);
          this.lines[index].endTime = this.formatTime(newEnd);
          this.syncLineTimes(index, 'start');
          this.syncLineTimes(index, 'end');
        },

        selectLine(index) {
          if (!this.lines[index] || this.lines[index].startTime === null) return;
          this.selectedIndex = index;
          this.wsRegions.clearRegions();

          let { startTime, endTime } = this.lines[index];
          if (endTime === null) {
            let defaultEnd = null;
            if (index === this.lines.length - 1) {
              defaultEnd = this.totalDuration;
            } else {
              defaultEnd = this.getNextPause(startTime);
            }
            if (defaultEnd !== null) {
              endTime = defaultEnd;
              this.lines[index].endTime = endTime;
              if (index < this.lines.length - 1) this.lines[index + 1].startTime = endTime;
            } else if (index < this.lines.length - 1 && typeof startTime === 'number') {
              // No hints: propose a small default window using FIRST_WINDOW_FACTOR
              const constraints = this.getLineConstraints(index);
              const proposed = this.formatTime(
                Math.min(
                  constraints.maxEnd,
                  Math.max(constraints.minEnd, startTime + this.MIN_DURATION * this.FIRST_WINDOW_FACTOR)
                )
              );
              endTime = proposed;
              this.lines[index].endTime = endTime;
              this.lines[index + 1].startTime = endTime;
            }
          }

          // If we still don't have an endTime (no pause hints), do not create a region
          if (endTime === null) {
            this.currentRegion = null;
            this.wsRegions.clearRegions();
            if (this.wsReady) this.wavesurfer.setTime(startTime ?? 0);
            return;
          }

          this.createRegion(index, startTime, endTime);
          if (this.wsReady) this.wavesurfer.setTime(startTime);
        },

        createRegion(index, start, end) {
          if (index === this.lines.length - 1) end = this.totalDuration;
          this.currentRegion = this.wsRegions.addRegion({
            start, end,
            drag: false,
            resizeStart: !this.isFirstLine,
            resizeEnd: !(this.isLastLine || index === this.lines.length - 1),
            color: 'rgba(79, 74, 133, 0.2)'
          });

          this.currentRegion.on('update', () => {
            let newStart = this.currentRegion.start;
            let newEnd = this.currentRegion.end;

            if (newEnd - newStart < this.MIN_DURATION) {
              if (newStart !== this.lines[index].startTime) newStart = newEnd - this.MIN_DURATION;
              else newEnd = newStart + this.MIN_DURATION;
              this.currentRegion.setOptions({ start: newStart, end: newEnd });
            }

            const constraints = this.getLineConstraints(index);
            if (newStart < constraints.minStart) { newStart = constraints.minStart; this.currentRegion.setOptions({ start: newStart }); }
            if (newEnd > constraints.maxEnd) { newEnd = constraints.maxEnd; this.currentRegion.setOptions({ end: newEnd }); }
          });

          this.currentRegion.on('update-end', () => {
            this.updateModelFromRegion(index);
          });
        },

        // Utils
        getNextPause(startTime) {
          if (!Array.isArray(this.pausesArray) || this.pausesArray.length === 0) return null;
          const st = typeof startTime === 'number' ? startTime : 0;
          const nextPause = this.pausesArray.find(pause => pause > st);
          return nextPause ? this.formatTime(nextPause) : null;
        },
        formatTime(time) { return parseFloat(Number(time).toFixed(2)); },

        validateTime(index, type) {
          const line = this.lines[index];
          const value = type === 'start' ? line.startTime : line.endTime;
          let correctedValue = this.formatTime(value);
          if (type === 'start' && correctedValue < 0) correctedValue = 0;
          const c = this.getLineConstraints(index);
          const minC = type === 'start' ? c.minStart : c.minEnd;
          const maxC = type === 'start' ? c.maxStart : c.maxEnd;
          if (correctedValue < minC) correctedValue = this.formatTime(minC);
          if (correctedValue > maxC) correctedValue = this.formatTime(maxC);
          if (type === 'start') line.startTime = correctedValue; else line.endTime = correctedValue;
          this.syncLineTimes(index, type);
          if (this.currentRegion && this.selectedIndex === index) this.updateRegionFromModel(index);
          return correctedValue;
        },
        updateRegionFromModel(index) {
          if (this.currentRegion && this.selectedIndex === index) {
            const { startTime, endTime } = this.lines[index];
            this.currentRegion.setOptions({ start: startTime, end: endTime });
          }
        },
        updateModelFromRegion(index) {
          if (this.currentRegion && this.selectedIndex === index) {
            this.lines[index].startTime = this.formatTime(this.currentRegion.start);
            this.lines[index].endTime = this.formatTime(this.currentRegion.end);
            this.syncLineTimes(index, 'start');
            this.syncLineTimes(index, 'end');
          }
        },
        selectPreviousLine() {
          if (!this.isFirstLine) {
            this.selectLine(this.selectedIndex - 1);
            // allow region to be (re)created before playing
            setTimeout(() => this.playCurrentRegionOnce(), 60);
          }
        },
        selectNextLine() {
          if (!this.isLastLine) {
            this.selectLine(this.selectedIndex + 1);
            setTimeout(() => this.playCurrentRegionOnce(), 60);
          }
        },
        playCurrentRegionOnce() {
          if (!this.wsReady || !this.currentRegion) return;
          const { start, end } = this.currentRegion;
          if (typeof start === 'number' && typeof end === 'number' && end > start) {
            try {
              this.wavesurfer.play(start, end);
              this.isPlaying = true;
            } catch (e) {
              console.warn(e);
            }
          }
        },
        togglePlayPause() {
          if (!this.wsReady || !this.currentRegion) return;
          if (this.isPlaying) { this.wavesurfer.pause(); this.isPlaying = false; }
          else {
            const { start, end } = this.currentRegion;
            if (typeof start === 'number' && typeof end === 'number' && end > start) {
              try {
                this.wavesurfer.play(start, end);
                this.isPlaying = true;
              } catch (e) {
                console.warn(e);
              }
            }
          }
        },
        saveCurrentRegion() { if (!this.isLastLine) this.saveLine(this.selectedIndex).catch(err => this.notifyError(err)); },
        applyZoom() {
          const z = Math.max(this.ZOOM_MIN, Math.min(this.ZOOM_MAX, this.zoom));
          this.zoom = z;
          if (this.wavesurfer && this.wsReady) {
            this.wavesurfer.zoom(z);
          }
        },
        zoomIn() {
          this.zoom = Math.min(this.ZOOM_MAX, this.zoom + this.ZOOM_STEP);
          this.applyZoom();
        },
        zoomOut() {
          this.zoom = Math.max(this.ZOOM_MIN, this.zoom - this.ZOOM_STEP);
          this.applyZoom();
        },
        fitAll() {
          const el = document.getElementById('waveform');
          const w = el ? el.clientWidth : 800;
          const duration = this.totalDuration || 1;
          const pxPerSec = Math.max(this.ZOOM_MIN, Math.min(this.ZOOM_MAX, Math.round(w / duration)));
          this.zoom = pxPerSec;
          this.applyZoom();
        },
        fitRegion() {
          if (!this.currentRegion) return;
          const el = document.getElementById('waveform');
          const w = el ? el.clientWidth : 800;
          const dur = Math.max(this.MIN_DURATION, this.currentRegion.end - this.currentRegion.start);
          const pxPerSec = Math.max(this.ZOOM_MIN, Math.min(this.ZOOM_MAX, Math.round(w / dur)));
          this.zoom = pxPerSec;
          this.applyZoom();
          // Перейти к началу региона
          if (this.wsReady) this.wavesurfer.setTime(this.currentRegion.start);
        },
        togglePlayPause() {
          if (!this.currentRegion) return;
          if (this.isPlaying) { this.wavesurfer.pause(); this.isPlaying = false; }
          else { this.wavesurfer.play(this.currentRegion.start, this.currentRegion.end); this.isPlaying = true; }
        },
        saveCurrentRegion() { if (!this.isLastLine) this.saveLine(this.selectedIndex).catch(err => this.notifyError(err)); },
      };
    }

    document.addEventListener('alpine:init', () => { /* Alpine component registered via project() above */ });
  </script>
</body>
</html>
