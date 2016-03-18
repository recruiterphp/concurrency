(ns knossos-onebip.core
  (:gen-class)
  (:require [knossos.core :as core]
            [knossos-onebip.model :as model]
            [clojure.java.io :as io]
            [clojure-csv.core :refer [parse-csv]]))

(defn history-from-file [path]
  (let [lines (-> (io/file path)
                  io/reader
                  parse-csv)]
    (map (fn [[_ process type f]]
           {:type (keyword type)
            :process (keyword process)
            :f (keyword f)
            :value nil})
         lines)))

(defn check-history [model-fn filename]
  (let [lock (model-fn)
        history (history-from-file filename)]
    (core/analysis lock history)))

(defn -main
  "Pass a model name (e.g. 'mongo-lock' and a CSV file.
  with the structure 'timestamp,process,type,f' (e.g. '1458232058222295,pp0,invoke|ok|fail,acquire')"
  [& args]
  (let [[model-name filename] args]
    (check-history (ns-resolve (find-ns 'knossos-onebip.model) (symbol model-name))
                    filename)))
